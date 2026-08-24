<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BillingService;
use App\Services\EpayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending {--minutes=30 : 超过多少分钟未支付则关单}';

    protected $description = '关闭超时未支付的订单（关单前先查网关，防误杀已付款漏回调的单）';

    public function handle(EpayService $epay, BillingService $billing): int
    {
        $minutes = (int) $this->option('minutes');
        $count = 0;

        Order::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($epay, $billing, &$count) {
                foreach ($orders as $order) {
                    // 关单前最后确认：网关已支付则补发货，绝不误杀
                    if ($epay->configured() && $epay->isPaidOnGateway((string) $order->id)) {
                        try {
                            $billing->settleOrder($order, 'epay');
                        } catch (\Throwable $e) {
                            Log::warning('expire settle failed', ['order' => $order->id, 'err' => $e->getMessage()]);
                        }

                        continue;
                    }

                    // 行锁关单，防与支付竞态
                    $cancelled = DB::transaction(function () use ($order) {
                        $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();
                        if (! $locked || $locked->status !== 'pending') {
                            return false;
                        }
                        $locked->update(['status' => 'cancelled']);

                        return true;
                    });
                    if ($cancelled) {
                        $count++;
                    }
                }
            });

        $this->info("已关闭超时订单 {$count} 笔");

        return self::SUCCESS;
    }
}
