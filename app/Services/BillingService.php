<?php

namespace App\Services;

use App\Models\BalanceLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Payback;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    /**
     * 发货：把套餐权益应用到用户。计费核心。
     *
     * 规则：
     * - 流量设为套餐流量、已用清零
     * - 等级设为套餐等级、限速/设备数按套餐
     * - 到期时间：未过期则从原到期日叠加，已过期则从 now 起算
     * - 重置：monthly 型每 30 天从发货日刷新；none 型总量不重置(next_reset_at=null)
     */
    public function deliver(User $user, Plan $plan): void
    {
        DB::transaction(function () use ($user, $plan) {
            $base = ($user->class_expire && $user->class_expire->isFuture())
                ? $user->class_expire->copy()
                : now();

            $quota = $plan->transfer_gb * (1024 ** 3);
            $user->update([
                'transfer_enable' => $quota,
                'base_transfer_enable' => $quota,   // 重置基准，用于月度归位、抹掉加油包
                'u' => 0,
                'd' => 0,
                'class' => $plan->class,
                'class_expire' => $base->addDays($plan->duration_days),
                // monthly：发货日 + 1 个月(月末不溢出)；none(总量型)：不重置
                'next_reset_at' => $plan->resetsMonthly() ? now()->addMonthNoOverflow() : null,
                'node_speed_limit' => $plan->speed_limit,
                'node_ip_limit' => $plan->ip_limit,
            ]);
        });
    }

    /** 流量包(加油包)：立即给当前周期加流量，不改等级/到期/重置 */
    public function applyDataPack(User $user, Plan $plan): void
    {
        $user->increment('transfer_enable', $plan->transfer_gb * (1024 ** 3));
    }

    /**
     * 当前已拥有权益的“有效终点”：当前到期日 + 所有排队订单时长的累加。
     * 用于计算新购套餐的排队生效时间。
     */
    public function effectiveEnd(User $user): \Illuminate\Support\Carbon
    {
        $end = ($user->class_expire && $user->class_expire->isFuture())
            ? $user->class_expire->copy()
            : now();

        foreach ($user->orders()->where('status', 'queued')->with('plan')->get() as $q) {
            $end = $end->addDays($q->plan->duration_days ?? 0);
        }

        return $end;
    }

    /**
     * 标记订单已支付并结算。
     * - 库存/优惠券/返利在支付时结算
     * - 流量包：立即加流量
     * - 普通套餐：当前有效则排队(status=queued, activate_at)，否则立即发货
     */
    public function completeOrder(Order $order, string $payMethod): void
    {
        DB::transaction(function () use ($order, $payMethod) {
            $order->update(['pay_method' => $payMethod, 'paid_at' => now()]);

            // 支付即结算的部分
            if ($order->plan && $order->plan->stock > 0) {
                $order->plan->decrement('stock');
            }
            if ($order->coupon_id && $order->coupon) {
                $order->coupon->increment('used');
            }
            $this->payback($order);

            $user = $order->user;
            $plan = $order->plan;

            if ($plan->is_data_pack) {
                // 流量包：立即生效，不排队、不改到期
                $this->applyDataPack($user, $plan);
                $order->update(['status' => 'paid', 'delivered_at' => now()]);

                return;
            }

            $end = $this->effectiveEnd($user);
            if ($end->isFuture()) {
                // 当前套餐(或已排队套餐)未到期 → 排队，等到期自动激活
                $order->update(['status' => 'queued', 'activate_at' => $end]);
            } else {
                $this->deliver($user, $plan);
                $order->update(['status' => 'paid', 'delivered_at' => now(), 'activate_at' => now()]);
            }
        });
    }

    /** 激活一笔到期的排队订单（由定时任务调用） */
    public function activate(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->deliver($order->user, $order->plan);
            $order->update(['status' => 'paid', 'delivered_at' => now()]);
        });
    }

    /**
     * 立即结束当前套餐：把当前套餐置为过期，并让排队队列从现在起重新排期，
     * 队首若已到期则立即激活。用于“流量用完/提前换套餐”。
     */
    public function endCurrentPackage(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update(['class_expire' => now(), 'next_reset_at' => null]);

            // 队列从现在起重新排期
            $base = now();
            $queued = $user->orders()->where('status', 'queued')->with('plan')->orderBy('activate_at')->get();
            foreach ($queued as $order) {
                $order->update(['activate_at' => $base]);
                $base = $base->copy()->addDays($order->plan->duration_days ?? 0);
            }

            // 队首（activate_at=now）立即激活
            $first = $user->orders()->where('status', 'queued')->where('activate_at', '<=', now())->orderBy('activate_at')->first();
            if ($first) {
                $this->activate($first);
            }
        });
    }

    /** 余额支付一笔待支付订单：校验余额→扣款记流水→发货 */
    public function payWithBalance(Order $order): void
    {
        $user = $order->user;
        if ($user->money < $order->amount) {
            throw ValidationException::withMessages(['method' => '余额不足，请先充值']);
        }

        DB::transaction(function () use ($user, $order) {
            $user->decrement('money', $order->amount);
            BalanceLog::create([
                'user_id' => $user->id,
                'amount' => -$order->amount,
                'type' => 'consume',
                'balance_after' => $user->fresh()->money,
                'remark' => "购买套餐 #{$order->id}",
            ]);
            $this->completeOrder($order, 'balance');
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
