<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Recharge;
use App\Services\BillingService;
use App\Services\EpayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePayments extends Command
{
    protected $signature = 'payment:reconcile';

    protected $description = '支付对账：对回调可能漏单的待支付订单/充值单主动查单，已支付则补发货/补到账';

    public function handle(EpayService $epay, BillingService $billing): int
    {
        if (! $epay->configured()) {
            $this->info('网关未配置，跳过对账');

            return self::SUCCESS;
        }

        $count = 0;

        // 给异步回调留 2 分钟窗口；只查近 1 天内的挂单，避免翻旧账
        Order::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($epay, $billing, &$count) {
                foreach ($orders as $order) {
                    if (! $epay->isPaidOnGateway($order->order_no)) {
                        continue;
                    }
                    try {
                        if ($billing->settleOrder($order, 'epay')) {   // 幂等
                            $count++;
                            // 回调丢了、钱已经收了 —— 这条必须留痕:
                            // 它是"用户付了钱但系统当时没反应"的唯一证据
                            system_audit('order.reconciled', sprintf(
                                '订单 %s 对账发现网关已付款，自动补发货（金额 ¥%s）',
                                $order->order_no, number_format((float) $order->amount, 2),
                            ), $order);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('reconcile settle failed', ['order' => $order->id, 'err' => $e->getMessage()]);
                    }
                }
            });

        // 充值单同样可能丢回调:主动查单,已支付则补到账(creditRecharge 幂等)
        $rechargeCount = 0;
        Recharge::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->chunkById(100, function ($recharges) use ($epay, $billing, &$rechargeCount) {
                foreach ($recharges as $recharge) {
                    if ($epay->isPaidOnGateway($recharge->order_no) !== true) {
                        continue;   // 未支付/不确定都跳过,留待下次
                    }
                    try {
                        $billing->creditRecharge($recharge, null);   // 幂等
                        $rechargeCount++;
                        // 与订单补发货同一类:钱已经收了而系统当时没反应,
                        // 这条是唯一的证据
                        system_audit('recharge.reconciled', sprintf(
                            '充值单 %s 对账发现网关已付款，自动补到账（金额 ¥%s）',
                            $recharge->order_no, number_format((float) $recharge->amount, 2),
                        ), $recharge);
                    } catch (\Throwable $e) {
                        Log::warning('reconcile recharge failed', ['recharge' => $recharge->id, 'err' => $e->getMessage()]);
                    }
                }
            });

        $this->info("对账补发货 {$count} 笔，补到账充值 {$rechargeCount} 笔");

        return self::SUCCESS;
    }
}
