<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\EpayService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShopApiController extends Controller
{
    private const PERIOD_LABELS = ['month' => '1月', 'quarter' => '3月', 'half_year' => '6月', 'year' => '12月'];

    private const PERIOD_MONTHS = ['month' => 1, 'quarter' => 3, 'half_year' => 6, 'year' => 12];

    /** 在线渠道(与网页版一致);实际能否在线支付由「配了网关 或 本地/测试」决定 */
    private const ONLINE_METHODS = ['alipay' => '支付宝', 'wechat' => '微信支付', 'usdt' => 'USDT'];

    public function __construct(private OrderService $orders) {}

    /** GET /api/plans —— 在售套餐目录(普通套餐按名归组含各时长 + 流量包区) */
    public function index()
    {
        $onSale = Plan::where('on_sale', true)->orderBy('sort')->get();

        $dataPacks = $onSale->where('is_data_pack', true)->map(fn (Plan $p) => [
            'plan_id' => $p->id,
            'name' => $p->name,
            'transfer_gb' => (int) $p->transfer_gb,
            'price' => (float) $p->price,
            'sold_out' => $p->stock === 0,
        ])->values();

        $groups = $onSale->where('is_data_pack', false)
            ->groupBy('name')
            ->map(function ($rows) {
                $gb = (int) $rows->first()->transfer_gb;
                $durations = collect(self::PERIOD_LABELS)
                    ->map(function ($label, $period) use ($rows, $gb) {
                        $row = $rows->firstWhere('period', $period);
                        if (! $row) {
                            return null;
                        }
                        $months = self::PERIOD_MONTHS[$period];

                        return [
                            'plan_id' => $row->id,
                            'label' => $label,
                            'period' => $period,
                            'price' => (float) $row->price,
                            'days' => $row->duration_days,
                            'months' => $months,
                            'monthly_reset' => $row->resetsMonthly(),
                            'total_gb' => $row->resetsMonthly() ? $gb * $months : $gb,
                            'sold_out' => $row->stock === 0,
                        ];
                    })
                    ->filter()->values();

                return ['name' => $rows->first()->name, 'transfer_gb' => $gb, 'durations' => $durations];
            })
            ->filter(fn ($g) => $g['durations']->isNotEmpty())
            ->values();

        return response()->json(['ret' => 1, 'data' => ['groups' => $groups, 'data_packs' => $dataPacks]]);
    }

    /** GET /api/orders —— 我的订单历史(近30笔,最新在前) */
    public function orders(Request $request)
    {
        $orders = $request->user()->orders()->with('plan', 'coupon')
            ->latest()->limit(30)->get()
            ->map(fn (Order $o) => array_merge($this->orderPayload($o), [
                'created_at' => $o->created_at?->toDateTimeString(),
                'paid_at' => $o->paid_at?->toDateTimeString(),
                'activate_at' => $o->activate_at?->toDateTimeString(),
            ]))->values();

        return response()->json(['ret' => 1, 'data' => $orders]);
    }

    /** POST /api/subscription/end —— 立即结束当前套餐(仅单月套餐),让排队套餐生效 */
    public function endSubscription(Request $request, BillingService $billing)
    {
        $user = $request->user();
        if (! $user->canEndCurrentPackage()) {
            return response()->json(['ret' => 0, 'msg' => '当前套餐不可立即结束（仅单月套餐可用）'], 422);
        }

        $billing->endCurrentPackage($user);
        $activated = $user->fresh()->hasActivePackage();

        return response()->json([
            'ret' => 1,
            'msg' => $activated ? '当前套餐已结束，排队套餐已生效' : '当前套餐已结束',
        ]);
    }

    /** POST /api/order/create —— 下单生成待支付订单 */
    public function create(Request $request)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);

        try {
            $order = $this->orders->createPending($request->user(), Plan::findOrFail($data['plan_id']));
        } catch (ValidationException $e) {
            return response()->json(['ret' => 0, 'msg' => $e->validator->errors()->first()], 422);
        }

        return response()->json(['ret' => 1, 'data' => $this->orderPayload($order->fresh()->load('plan', 'coupon'))]);
    }

    /** GET /api/order/{order} —— 收银台结算信息 */
    public function show(Order $order, Request $request, BillingService $billing, EpayService $epay)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '订单不存在'], 404);
        }

        $user = $request->user();
        $order->load('plan', 'coupon');

        // 普通套餐且当前有生效套餐 → 本单支付后排队,预计生效时间
        $queuedActivateAt = null;
        if (! $order->plan->is_data_pack) {
            $end = $billing->effectiveEnd($user);
            if ($end->isFuture()) {
                $queuedActivateAt = $end->toDateTimeString();
            }
        }

        return response()->json(['ret' => 1, 'data' => array_merge($this->orderPayload($order), [
            'balance' => (float) $user->money,
            'queued_activate_at' => $queuedActivateAt,
            'online_pay' => $epay->configured() || app()->environment(['local', 'testing']),
            'coupon_notes' => \App\Models\Coupon::checkoutVisible()->map(fn ($c) => [
                'code' => $c->code, 'note' => $c->note,
            ])->values(),
        ])]);
    }

    /** POST /api/order/{order}/coupon —— 应用/移除优惠码(留空移除),返回重算后金额 */
    public function coupon(Order $order, Request $request)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '订单不存在'], 404);
        }
        if ($order->status !== 'pending') {
            return response()->json(['ret' => 0, 'msg' => '该订单已处理，无法修改'], 409);
        }

        $data = $request->validate(['coupon' => ['nullable', 'string', 'max:32']]);

        try {
            $this->orders->applyCoupon($order, $data['coupon'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['ret' => 0, 'msg' => $e->validator->errors()->first()], 422);
        }

        return response()->json(['ret' => 1, 'data' => $this->orderPayload($order->fresh()->load('plan', 'coupon'))]);
    }

    /** POST /api/order/{order}/pay —— 统一支付:余额直扣 / 在线跳网关 / 0元直发 */
    public function pay(Order $order, Request $request, BillingService $billing, EpayService $epay)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '订单不存在'], 404);
        }
        if ($order->status !== 'pending') {
            return response()->json(['ret' => 0, 'msg' => '该订单已处理，无需支付'], 409);
        }

        // 0 元订单(优惠券抵满)直接发货
        if ((float) $order->amount <= 0) {
            $billing->settleOrder($order, 'free');

            return response()->json(['ret' => 1, 'data' => ['status' => 'delivered'], 'msg' => '订单已发货']);
        }

        $data = $request->validate([
            'method' => ['required', 'in:balance,'.implode(',', array_keys(self::ONLINE_METHODS))],
        ]);

        // 余额支付:锁内校验,不足抛错
        if ($data['method'] === 'balance') {
            try {
                $billing->payWithBalance($order);
            } catch (ValidationException $e) {
                return response()->json(['ret' => 0, 'msg' => $e->validator->errors()->first()], 402);
            }

            return response()->json(['ret' => 1, 'data' => ['status' => 'delivered'], 'msg' => '余额支付成功，套餐已到账']);
        }

        // 在线渠道:配了网关 → 返回收银台 URL,由 App 打开;回调发货
        if ($epay->configured()) {
            return response()->json(['ret' => 1, 'data' => [
                'status' => 'redirect',
                'pay_url' => $epay->payUrl($order, $data['method']),
            ]]);
        }

        // 未配网关:仅本地/测试允许模拟直付;生产必须报错,严禁零成本到账
        if (! app()->environment(['local', 'testing'])) {
            return response()->json(['ret' => 0, 'msg' => '在线支付暂不可用，请稍后再试或联系客服'], 503);
        }
        $billing->settleOrder($order, $data['method']);

        return response()->json(['ret' => 1, 'data' => ['status' => 'delivered', 'mock' => true], 'msg' => '支付成功，套餐已到账（模拟）']);
    }

    /** POST /api/order/{order}/cancel —— 取消待支付订单(行锁防与支付竞态) */
    public function cancel(Order $order, Request $request)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '订单不存在'], 404);
        }

        $cancelled = \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return false;
            }
            $locked->update(['status' => 'cancelled']);

            return true;
        });

        if (! $cancelled) {
            return response()->json(['ret' => 0, 'msg' => '订单状态异常，无法取消'], 409);
        }

        return response()->json(['ret' => 1, 'msg' => '订单已取消']);
    }

    /** 订单出参 */
    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status,
            'amount' => (float) $order->amount,
            'period' => $order->period,
            'plan' => $order->plan ? [
                'id' => $order->plan->id,
                'name' => $order->plan->name,
                'price' => (float) $order->plan->price,
                'transfer_gb' => (int) $order->plan->transfer_gb,
                'is_data_pack' => (bool) $order->plan->is_data_pack,
            ] : null,
            'coupon' => $order->coupon ? ['code' => $order->coupon->code] : null,
        ];
    }
}
