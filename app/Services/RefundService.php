<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 订单退款。
 *
 * `[!!]` 这个服务【只保证钱这一侧准确】，不假装能回滚权益。
 *
 * 理由在数据里：deliver() 是覆盖写 —— transfer_enable / class / class_expire
 * 被新值直接盖掉、u/d 清零，而**覆盖前的值没有任何地方留存**。
 * 于是"自动撤销权益"只有两种实现：猜一个旧值，或把用户打回零。
 * 两种都是错的，而且错了不会报错，只会变成一个用户投诉。
 *
 * 所以分工是：
 *     钱      精确 —— 金额、时间、原因、操作人，全部落库并审计
 *     券      精确 —— 当初占用的那一次用量释放掉（条件与占用时完全对称）
 *     权益    交给人 —— 只提供一个可靠动作「立即结束当前套餐」，并说清楚为什么不自动做
 *     库存    不动 —— 见下方说明
 */
class RefundService
{
    /** 哪些状态可以退。已取消/已退款的不能再退。 */
    public const REFUNDABLE = ['paid', 'queued'];

    /**
     * @param  bool  $endPackage  是否同时立即结束该用户当前套餐
     * @return array{ok:bool,message:string}
     */
    public function refund(Order $order, float $amount, string $reason, bool $endPackage): array
    {
        if (! in_array($order->status, self::REFUNDABLE, true)) {
            return ['ok' => false, 'message' => "状态为「{$order->status}」的订单不能退款"];
        }
        if ($amount <= 0 || $amount > (float) $order->amount) {
            return ['ok' => false, 'message' => '退款金额必须大于 0 且不超过订单金额 '.$order->amount];
        }

        $notes = [];
        DB::transaction(function () use ($order, $amount, $reason, $endPackage, &$notes) {
            $wasDelivered = $order->delivered_at !== null;

            $order->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $amount,
                'refund_reason' => $reason,
            ]);

            // `[!]` 券的释放与占用【条件完全对称】：占用时的判据是 coupon_id 有值，
            // 这里也是。不对称的话会出现"退了款但券还占着"或"退一次放两次"。
            if ($order->coupon_id && ($c = Coupon::find($order->coupon_id)) && $c->used > 0) {
                $c->decrement('used');
                $notes[] = '已释放优惠券一次用量';
            }

            // `[!!]` 库存【不动】。占用时的条件是"结算那一刻 stock > 0"，
            // 而那一刻的值现在无从得知 —— 当时若是 0（不限量或已售罄）根本没扣过，
            // 现在加回去就是凭空多出一件。宁可少加，也不要静默加错。
            if ($order->plan && $order->plan->stock > 0) {
                $notes[] = '库存未自动加回（当初是否扣减已无从判断，需要就手动改）';
            }

            if ($endPackage && $wasDelivered) {
                $user = User::whereKey($order->user_id)->lockForUpdate()->first();
                app(BillingService::class)->endCurrentPackage($user);
                $notes[] = '已立即结束该用户当前套餐';
            } elseif ($wasDelivered) {
                $notes[] = '权益【未】撤销 —— 用户当前套餐仍然有效';
            } else {
                $notes[] = '这笔订单尚未发货，没有权益需要处理';
            }
        });

        return ['ok' => true, 'message' => '已退款 ¥'.number_format($amount, 2)
            .'。'.implode('；', $notes)];
    }

    /**
     * 退款【不会】自动做的事，用于在界面上提前说清楚。
     *
     * `[!]` 把做不到的事明说出来，比事后解释便宜得多 ——
     * 人是按界面上写的去理解系统行为的。
     */
    public static function caveats(Order $order): array
    {
        $out = [];
        if ($order->delivered_at) {
            $out[] = '这笔订单【已发货】。退款不会自动撤销已发放的权益 —— '
                .'发货是覆盖写（流量配额、等级、到期日直接被盖掉，已用流量清零），'
                .'覆盖前的值没有留存，所以没法准确还原。'
                .'要收回就勾选下面的「立即结束当前套餐」。';
        }
        if ($order->plan && $order->plan->stock > 0) {
            $out[] = '库存不会自动加回：当初是否扣减取决于结算那一刻的库存值，现在无从判断。';
        }
        if ($order->coupon_id) {
            $out[] = '所用优惠券的一次用量会被释放。';
        }

        return $out;
    }
}
