<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Services\BillingService;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /** GET /user/shop */
    public function index()
    {
        $periodLabels = ['month' => '1月', 'quarter' => '3月', 'half_year' => '6月', 'year' => '12月'];
        $periodMonths = ['month' => 1, 'quarter' => 3, 'half_year' => 6, 'year' => 12];

        $onSale = Plan::where('on_sale', true)->orderBy('sort')->get();

        // 流量包(加油包)单独成区
        $dataPacks = $onSale->where('is_data_pack', true)->map(fn ($p) => [
            'plan_id' => $p->id,
            'name' => $p->name,
            'transfer_gb' => (int) $p->transfer_gb,
            'price' => rtrim(rtrim(number_format($p->price, 2), '0'), '.'),
            'stock' => $p->stock,
            'sold_out' => $p->stock === 0,
        ])->values();

        // 普通套餐同名归组，组内按 1/3/6/12 月排列各时长
        $groups = $onSale->where('is_data_pack', false)
            ->groupBy('name')
            ->map(function ($rows) use ($periodLabels, $periodMonths) {
                $gb = (int) $rows->first()->transfer_gb;
                $durations = collect($periodLabels)
                    ->map(function ($label, $period) use ($rows, $periodMonths, $gb) {
                        $row = $rows->firstWhere('period', $period);
                        if (! $row) {
                            return null;
                        }

                        $months = $periodMonths[$period];

                        return [
                            'plan_id' => $row->id,
                            'label' => $label,
                            'price' => rtrim(rtrim(number_format($row->price, 2), '0'), '.'),
                            'days' => $row->duration_days,
                            'months' => $months,
                            'monthly_reset' => $row->resetsMonthly(),
                            // monthly：X个月总计=月配额×月数；none：总量就是 transfer_gb
                            'total_gb' => $row->resetsMonthly() ? $gb * $months : $gb,
                            'stock' => $row->stock,
                            'sold_out' => $row->stock === 0,
                        ];
                    })
                    ->filter()->values();

                return ['benefits' => $rows->first(), 'durations' => $durations];
            })
            ->filter(fn ($g) => $g['durations']->isNotEmpty())
            ->values();

        return view('user.shop', ['groups' => $groups, 'dataPacks' => $dataPacks]);
    }

    /** POST /user/order/create —— 下单（生成 pending 订单，跳收银台结算） */
    public function createOrder(Request $request)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);

        $plan = Plan::findOrFail($data['plan_id']);

        if (! $plan->on_sale || $plan->stock === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plan_id' => '该套餐已售罄或已下架']);
        }

        // 去重：同套餐已有待支付订单则复用，避免堆积
        $existing = Order::where('user_id', auth()->id())
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        if ($existing) {
            return redirect("/user/order/{$existing->id}");
        }

        $order = Order::create([
            'user_id' => auth()->id(),
            'plan_id' => $plan->id,
            'amount' => (float) $plan->price,   // 收银台可再抵扣优惠券
            'status' => 'pending',
            'period' => $plan->period,
        ]);

        return redirect("/user/order/{$order->id}");
    }

    /** GET /user/order/{order} —— 收银台结算页 */
    public function checkout(Order $order, BillingService $billing)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user/wallet')->with('status', '该订单已处理，无需支付');
        }

        $user = auth()->user();
        // 普通套餐且当前有生效套餐 → 本单支付后排队，预计生效时间
        $queuedActivateAt = null;
        if (! $order->plan->is_data_pack) {
            $end = $billing->effectiveEnd($user);
            if ($end->isFuture()) {
                $queuedActivateAt = $end;
            }
        }

        return view('user.checkout', [
            'order' => $order->load('plan', 'coupon'),
            'user' => $user,
            'couponNotes' => \App\Models\Coupon::checkoutVisible(),
            'queuedActivateAt' => $queuedActivateAt,
        ]);
    }

    /** POST /user/order/{order}/cancel —— 取消待支付订单（行锁防与支付竞态） */
    public function cancelOrder(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $cancelled = \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return false;
            }
            $locked->update(['status' => 'cancelled']);

            return true;
        });

        abort_unless($cancelled, 403);

        return redirect('/user/wallet')->with('status', '订单已取消');
    }

    /** POST /user/order/{order}/coupon —— 收银台应用/移除优惠码（按原价重算，支付成功才计 used） */
    public function applyCoupon(Order $order, Request $request)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_if($order->status !== 'pending', 403);

        $data = $request->validate(['coupon' => ['nullable', 'string', 'max:32']]);
        $base = (float) $order->plan->price;

        // 留空 = 移除优惠码，恢复原价
        if (empty($data['coupon'])) {
            $order->update(['coupon_id' => null, 'amount' => $base]);

            return redirect("/user/order/{$order->id}")->with('status', '已移除优惠码');
        }

        $coupon = \App\Models\Coupon::where('code', $data['coupon'])->first();
        if (! $coupon || ! $coupon->isUsable()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['coupon' => '优惠券无效或已过期']);
        }
        if (! $coupon->appliesToPeriod($order->period)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['coupon' => '此优惠码不适用于该套餐时长']);
        }

        $order->update(['coupon_id' => $coupon->id, 'amount' => $coupon->apply($base)]);

        return redirect("/user/order/{$order->id}")->with('status', "优惠码已应用，应付 ¥{$order->fresh()->amount}");
    }

    /** 在线支付渠道（无真实网关，均走模拟成功，记录渠道名） */
    private const ONLINE_METHODS = ['alipay' => '支付宝', 'wechat' => '微信支付', 'usdt' => 'USDT'];

    /** POST /user/order/{order}/pay —— 收银台统一支付：按所选方式发货 */
    public function pay(Order $order, Request $request, BillingService $billing, \App\Services\EpayService $epay)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_if($order->status !== 'pending', 403);

        // 0 元订单（优惠券抵满）直接发货，无需选支付方式
        if ((float) $order->amount <= 0) {
            $billing->settleOrder($order, 'free');

            return redirect('/user')->with('status', '订单已发货！');
        }

        $data = $request->validate([
            'method' => ['required', 'in:balance,'.implode(',', array_keys(self::ONLINE_METHODS))],
        ]);

        if ($data['method'] === 'balance') {
            $billing->payWithBalance($order);   // 锁内校验余额，不足会抛错误

            return redirect('/user')->with('status', '余额支付成功，套餐已到账！');
        }

        // 在线渠道：已配置网关则跳转支付，回调发货
        if ($epay->configured() && $epay->supports($data['method'])) {
            return redirect()->away($epay->payUrl($order, $data['method']));
        }

        // 未配置网关 → 模拟直付（开发/演示）
        $billing->settleOrder($order, $data['method']);
        $channel = self::ONLINE_METHODS[$data['method']];

        return redirect('/user')->with('status', "{$channel}支付成功，套餐已到账！（未配置网关，模拟）");
    }

    /** POST /user/subscription/end —— 立即结束当前套餐（仅单月套餐，让排队套餐生效） */
    public function endSubscription(BillingService $billing)
    {
        $user = auth()->user();
        if (! $user->canEndCurrentPackage()) {
            return back()->with('status', '当前套餐不可立即结束（仅单月套餐可用）');
        }

        $billing->endCurrentPackage($user);
        $activated = $user->fresh()->hasActivePackage();

        return back()->with('status', $activated ? '当前套餐已结束，排队套餐已生效' : '当前套餐已结束');
    }

    /** POST /user/order/{order}/mock-pay —— 模拟支付并发货（仅开发环境） */
    public function mockPay(Order $order, BillingService $billing)
    {
        abort_unless(app()->environment('local', 'testing'), 404);
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user')->with('status', '订单状态异常，无需支付');
        }

        $billing->settleOrder($order, 'mock');

        return redirect('/user')->with('status', '支付成功，套餐已到账！');
    }
}
