<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * 下单与优惠券的业务核心,网页版(ShopController)与客户端 API(ShopApiController)共用。
 * 只管「待支付订单」的生成与改价;真正的扣款/发货在 BillingService,支付跳转在 EpayService。
 */
class OrderService
{
    /**
     * 生成待支付订单:售罄/下架校验、流量包需有生效套餐、同套餐待支付去重(复用不堆积)。
     *
     * @throws ValidationException 售罄/下架 或 流量包无生效套餐时(键 plan_id)
     */
    public function createPending(User $user, Plan $plan): Order
    {
        if (! $plan->on_sale || $plan->stock === 0) {
            throw ValidationException::withMessages(['plan_id' => '该套餐已售罄或已下架']);
        }

        // 流量包需在有生效套餐时购买,否则加了流量也用不了(节点按到期日下发)
        if ($plan->is_data_pack && ! $user->hasActivePackage()) {
            throw ValidationException::withMessages(['plan_id' => '流量包需在有生效套餐时购买，请先购买套餐']);
        }

        $existing = Order::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        if ($existing) {
            return $existing;
        }

        return Order::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => (float) $plan->price,   // 收银台可再抵扣优惠券
            'status' => 'pending',
            'period' => $plan->period,
        ]);
    }

    /**
     * 收银台应用/移除优惠码,按原价重算(支付成功才计 used)。code 为空=移除,恢复原价。
     *
     * @throws ValidationException 优惠码无效/过期/不适用该时长时(键 coupon)
     */
    public function applyCoupon(Order $order, ?string $code): void
    {
        $base = (float) $order->plan->price;

        if (empty($code)) {
            $order->update(['coupon_id' => null, 'amount' => $base]);

            return;
        }

        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon || ! $coupon->isUsable()) {
            throw ValidationException::withMessages(['coupon' => '优惠券无效或已过期']);
        }
        if (! $coupon->appliesToPeriod($order->period)) {
            throw ValidationException::withMessages(['coupon' => '此优惠码不适用于该套餐时长']);
        }

        $order->update(['coupon_id' => $coupon->id, 'amount' => $coupon->apply($base)]);
    }
}
