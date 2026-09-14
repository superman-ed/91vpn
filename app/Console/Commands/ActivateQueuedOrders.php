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
                    $classBefore = (int) $order->user->class;
                    $expireBefore = $order->user->class_expire;
                    // `[!]` 只有【真发了货】才计数、才写审计。
                    // activate() 在并发下会幂等跳过并返回 false ——
                    // 那种情况记一条"自动发货"是假记录，比不记更糟。
                    if (! $billing->activate($order)) {
                        continue;
                    }
                    $count++;

                    $u = $order->user->fresh();
                    system_audit('order.auto_activate', sprintf(
                        '%s 排队订单 %s 到期自动发货：套餐「%s」，等级 %d → %d，到期 %s → %s',
                        $u->ident(), $order->order_no, $order->plan->name,
                        $classBefore, (int) $u->class,
                        $expireBefore?->format('Y-m-d') ?? '无',
                        $u->class_expire?->format('Y-m-d') ?? '无',
                    ), $order);
                }
            });

        $this->info("已激活 {$count} 笔排队订单");

        return self::SUCCESS;
    }
}
