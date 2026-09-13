<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BillingService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        // `[!!]` ?anomaly= 来自总览上的异常卡片。它【绕过普通筛选】——
        // 那几种形态本来就是"状态字段本身不可信"的情况,
        // 再叠一层按状态筛选只会把要找的订单筛掉。
        $anomaly = $request->query('anomaly');
        $scope = $anomaly ? \App\Services\OrderAnomalies::scope($anomaly) : null;

        $orders = ($scope ?? $this->filtered($status, $q, $from, $to))
            ->with(['user', 'plan'])
            ->latest()->paginate(30)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'anomaly' => $anomaly,
            'anomalyTitle' => $anomaly ? collect(app(\App\Services\OrderAnomalies::class)->check())
                ->firstWhere('key', $anomaly)['title'] ?? '异常订单' : null,
            'status' => $status,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'counts' => [
                'all' => Order::count(),
                'paid' => Order::where('status', 'paid')->count(),
                'pending' => Order::where('status', 'pending')->count(),
                'queued' => Order::where('status', 'queued')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
            ],
            // 营收=已收款订单:paid + queued(排队中的钱已收,paid_at 已写,只是套餐排队等激活),不能只算 paid
            'totalRevenue' => $totalRevenue = Order::whereIn('status', ['paid', 'queued'])->sum('amount'),
            'totalRebate' => $totalRebate = \App\Models\Payback::sum('amount'),
            'netProfit' => $totalRevenue - $totalRebate,
            'todayRevenue' => Order::whereIn('status', ['paid', 'queued'])->whereDate('paid_at', today())->sum('amount'),
        ]);
    }

    /** 共用筛选（index / export） */
    private function filtered($status, $q, $from = null, $to = null)
    {
        return Order::query()
            ->when(in_array($status, ['paid', 'pending', 'queued', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"));
                    if (ctype_digit((string) $q)) {
                        $w->orWhere('id', (int) $q);
                    }
                });
            })
            ->dateBetween($from, $to);
    }

    /** GET /admin/orders/export —— 按当前筛选导出订单 CSV */
    public function export(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $statusName = ['paid' => '已支付', 'pending' => '待支付', 'queued' => '排队中', 'cancelled' => '已取消'];
        $header = ['订单号', '用户', '套餐', '金额', '券抵扣', '状态', '支付方式', '网关交易号', '创建时间', '支付时间'];

        $rows = (function () use ($request, $from, $to, $statusName) {
            foreach ($this->filtered($request->query('status'), $request->query('q'), $from, $to)
                ->with(['user', 'plan', 'coupon'])->latest()->cursor() as $o) {
                $discount = $o->coupon_id && $o->plan ? max(0, (float) $o->plan->price - (float) $o->amount) : 0;
                yield [
                    $o->order_no,
                    $o->user?->email ?? '—',
                    $o->plan?->name ?? '—',
                    number_format((float) $o->amount, 2),
                    $discount > 0 ? number_format($discount, 2) : '',
                    $statusName[$o->status] ?? $o->status,
                    $o->pay_method ?? '',
                    $o->trade_no ?? '',
                    $o->created_at?->format('Y-m-d H:i:s'),
                    $o->paid_at?->format('Y-m-d H:i:s') ?? '',
                ];
            }
        })();

        audit('order.export', '导出订单 CSV');

        return csv_download('orders_'.now()->format('Ymd_His').'.csv', $header, $rows);
    }

    /** 手动标记已支付并发货(线下/补单用) */
    /**
     * 退款。
     *
     * `[!!]` 只保证【钱这一侧】准确。权益不自动撤销 —— 发货是覆盖写，
     * 覆盖前的值没有留存，自动还原只能靠猜。要收回就显式勾选「结束当前套餐」。
     * 把做不到的事明说出来，比事后解释便宜得多。
     */
    public function refund(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:200'],
            'end_package' => ['nullable'],
        ], [], ['amount' => '退款金额', 'reason' => '退款原因']);

        $r = app(\App\Services\RefundService::class)->refund(
            $order, (float) $data['amount'], $data['reason'], (bool) ($data['end_package'] ?? false));

        if ($r['ok']) {
            audit('order.refund', sprintf('订单 %s 退款 ¥%s：%s', $order->order_no,
                number_format((float) $data['amount'], 2), $data['reason']), $order);
        }

        return back()->with('status', $r['message']);
    }

    public function markPaid(Order $order, BillingService $billing)
    {
        if ($order->status !== 'pending') {
            return back()->with('status', '该订单非待支付状态');
        }
        // 走 settleOrder(行锁 + 锁内复查 pending),防止管理员并发双击重复发货(时长翻倍/库存多扣)
        $done = $billing->settleOrder($order, 'manual');
        if (! $done) {
            return back()->with('status', '该订单已被处理(可能重复提交)');
        }
        audit('order.mark_paid', "手动标记订单 {$order->order_no} 已支付并发货", $order);

        return back()->with('status', "订单 #{$order->id} 已标记支付并发货");
    }

    /** 取消待支付订单 */
    public function cancel(Order $order)
    {
        if ($order->status !== 'pending') {
            return back()->with('status', '仅待支付订单可取消');
        }
        $order->update(['status' => 'cancelled']);
        audit('order.cancel', "取消订单 {$order->order_no}", $order);

        return back()->with('status', "订单 #{$order->id} 已取消");
    }
}
