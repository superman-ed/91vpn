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
        $plans = Plan::where('on_sale', true)->orderBy('sort')->get();

        return view('user.shop', ['plans' => $plans]);
    }

    /** POST /user/order/create —— 下单（生成 pending 订单，支持优惠券） */
    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'coupon' => ['nullable', 'string', 'max:32'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $amount = (float) $plan->price;

        // 优惠券（选填）
        if (! empty($data['coupon'])) {
            $coupon = \App\Models\Coupon::where('code', $data['coupon'])->first();
            if (! $coupon || ! $coupon->isUsable()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['coupon' => '优惠券无效或已过期']);
            }
            $amount = $coupon->apply($amount);
            $coupon->increment('used');
        }

        $order = Order::create([
            'user_id' => auth()->id(),
            'plan_id' => $plan->id,
            'amount' => $amount,
            'status' => 'pending',
            'period' => $plan->period,
        ]);

        return redirect('/user/wallet')->with('status', "订单已创建：{$plan->name} ¥{$amount}，请在下方完成支付");
    }

    /** POST /user/order/{order}/mock-pay —— 模拟支付并发货 */
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
