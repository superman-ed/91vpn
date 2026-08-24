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

        // 同名套餐归为一组，组内按 1/3/6/12 月排列各时长价格
        $groups = Plan::where('on_sale', true)->orderBy('sort')->get()
            ->groupBy('name')
            ->map(function ($rows) use ($periodLabels, $periodMonths) {
                $monthlyGb = (int) $rows->first()->transfer_gb;
                $durations = collect($periodLabels)
                    ->map(function ($label, $period) use ($rows, $periodMonths, $monthlyGb) {
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
                            'total_gb' => $monthlyGb * $months,   // X个月总计 = 月配额 × 月数
                            'stock' => $row->stock,
                            'sold_out' => $row->stock === 0,
                        ];
                    })
                    ->filter()->values();

                return ['benefits' => $rows->first(), 'durations' => $durations];
            })
            ->filter(fn ($g) => $g['durations']->isNotEmpty())
            ->values();

        return view('user.shop', ['groups' => $groups]);
    }

    /** POST /user/order/create —— 下单（生成 pending 订单，跳收银台结算） */
    public function createOrder(Request $request)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);

        $plan = Plan::findOrFail($data['plan_id']);

        if (! $plan->on_sale || $plan->stock === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plan_id' => '该套餐已售罄或已下架']);
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
    public function checkout(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user/wallet')->with('status', '该订单已处理，无需支付');
        }

        return view('user.checkout', ['order' => $order->load('plan', 'coupon'), 'user' => auth()->user()]);
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

        $order->update(['coupon_id' => $coupon->id, 'amount' => $coupon->apply($base)]);

        return redirect("/user/order/{$order->id}")->with('status', "优惠码已应用，应付 ¥{$order->fresh()->amount}");
    }

    /** 在线支付渠道（无真实网关，均走模拟成功，记录渠道名） */
    private const ONLINE_METHODS = ['alipay' => '支付宝', 'wechat' => '微信支付', 'usdt' => 'USDT'];

    /** POST /user/order/{order}/pay —— 收银台统一支付：按所选方式发货 */
    public function pay(Order $order, Request $request, BillingService $billing)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_if($order->status !== 'pending', 403);

        $data = $request->validate([
            'method' => ['required', 'in:balance,'.implode(',', array_keys(self::ONLINE_METHODS))],
        ]);

        if ($data['method'] === 'balance') {
            $billing->payWithBalance($order);   // 余额不足会抛校验错误

            return redirect('/user')->with('status', '余额支付成功，套餐已到账！');
        }

        // 在线渠道：模拟支付成功
        $billing->completeOrder($order, $data['method']);
        $channel = self::ONLINE_METHODS[$data['method']];

        return redirect('/user')->with('status', "{$channel}支付成功，套餐已到账！");
    }

    /** POST /user/order/{order}/mock-pay —— 模拟支付并发货（开发用） */
    public function mockPay(Order $order, BillingService $billing)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user')->with('status', '订单状态异常，无需支付');
        }

        $billing->completeOrder($order, 'mock');

        return redirect('/user')->with('status', '支付成功，套餐已到账！');
    }
}
