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

        $orders = $this->filtered($status, $q, $from, $to)->with(['user', 'plan'])
            ->latest()->paginate(30)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
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
            'totalRevenue' => $totalRevenue = Order::where('status', 'paid')->sum('amount'),
            'totalRebate' => $totalRebate = \App\Models\Payback::sum('amount'),
            'netProfit' => $totalRevenue - $totalRebate,
            'todayRevenue' => Order::where('status', 'paid')->whereDate('paid_at', today())->sum('amount'),
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
    public function markPaid(Order $order, BillingService $billing)
    {
        if ($order->status !== 'pending') {
            return back()->with('status', '该订单非待支付状态');
        }
        $billing->completeOrder($order, 'manual');
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
