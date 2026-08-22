<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * 发货：把套餐权益应用到用户。计费核心。
     *
     * 规则：
     * - 流量累加（transfer_enable += 套餐流量）
     * - 等级设为套餐等级、限速/设备数按套餐
     * - 到期时间：未过期则从原到期日叠加，已过期则从 now 起算
     */
    public function deliver(User $user, Plan $plan): void
    {
        DB::transaction(function () use ($user, $plan) {
            $base = ($user->class_expire && $user->class_expire->isFuture())
                ? $user->class_expire->copy()
                : now();

            $user->update([
                'transfer_enable' => $user->transfer_enable + $plan->transfer_gb * (1024 ** 3),
                'class' => $plan->class,
                'class_expire' => $base->addDays($plan->duration_days),
                'node_speed_limit' => $plan->speed_limit,
                'node_ip_limit' => $plan->ip_limit,
            ]);
        });
    }

    /**
     * 标记订单已支付并发货。
     */
    public function completeOrder(Order $order, string $payMethod): void
    {
        DB::transaction(function () use ($order, $payMethod) {
            $order->update([
                'status' => 'paid',
                'pay_method' => $payMethod,
                'paid_at' => now(),
            ]);
            $this->deliver($order->user, $order->plan);
        });
    }
}
