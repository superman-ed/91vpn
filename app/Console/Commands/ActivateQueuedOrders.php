<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BillingService;
use Illuminate\Console\Command;

class ActivateQueuedOrders extends Command
{
    protected $signature = 'orders:activate-due';

    protected $description = '激活到期的排队订单：当前套餐过期后让排队套餐自动生效';

    public function handle(BillingService $billing): int
    {
        $count = 0;

        // 按预计生效时间顺序激活，保证同一用户多笔排队按序叠加
        Order::query()
            ->where('status', 'queued')
            ->whereNotNull('activate_at')
            ->where('activate_at', '<=', now())
            ->with('user', 'plan')
            ->orderBy('activate_at')
            ->chunkById(200, function ($orders) use ($billing, &$count) {
                foreach ($orders as $order) {
                    if (! $order->user || ! $order->plan) {
                        continue;
                    }
                    $billing->activate($order);
                    $count++;
                }
            });

        $this->info("已激活 {$count} 笔排队订单");

        return self::SUCCESS;
    }
}
