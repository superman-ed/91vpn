<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Payback;
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
            // 限量套餐扣库存（-1 表示无限，不扣）
            if ($order->plan && $order->plan->stock > 0) {
                $order->plan->decrement('stock');
            }
            $this->payback($order);
        });
    }

    /** 返利：下线付费给邀请人返佣（默认 20%，进余额） */
    private function payback(Order $order): void
    {
        $downline = $order->user;
        if (! $downline->ref_by) {
            return;
        }
        $inviter = User::find($downline->ref_by);
        if (! $inviter) {
            return;
        }

        $rate = 0.20;
        $amount = round($order->amount * $rate, 2);
        if ($amount <= 0) {
            return;
        }

        $inviter->increment('money', $amount);
        Payback::create([
            'user_id' => $inviter->id,
            'from_user_id' => $downline->id,
            'order_id' => $order->id,
            'amount' => $amount,
        ]);
    }
}
