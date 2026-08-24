<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetMonthlyTraffic extends Command
{
    protected $signature = 'traffic:reset-monthly';

    protected $description = '按开通日的月度周年刷新流量：清零到期用户的已用 u/d，并推进下次刷新日';

    public function handle(): int
    {
        $now = now();
        $count = 0;

        // 每人按自己的 next_reset_at 到期后刷新（非日历1号），每天跑一次检查
        User::query()
            ->where('class', '>', 0)
            ->whereNotNull('class_expire')
            ->where('class_expire', '>', $now)
            ->whereNotNull('next_reset_at')
            ->where('next_reset_at', '<=', $now)
            ->chunkById(500, function ($users) use ($now, &$count) {
                foreach ($users as $user) {
                    // 若停跑多日导致落后多个周期，循环推进到未来的下一个刷新日
                    $next = $user->next_reset_at->copy();
                    do {
                        $next = $next->addMonthNoOverflow();
                    } while ($next->lte($now));

                    $updates = ['u' => 0, 'd' => 0, 'next_reset_at' => $next];
                    // 归位：基础月配额刷新(不结转)，加油包按剩余跨月保留(先扣套餐、超出部分才吃加油包)
                    if ($user->base_transfer_enable > 0) {
                        $used = (int) $user->u + (int) $user->d;
                        $overflow = max(0, $used - (int) $user->base_transfer_enable);   // 本周期吃掉的加油包
                        $packLeft = max(0, (int) $user->pack_transfer - $overflow);
                        $updates['transfer_enable'] = (int) $user->base_transfer_enable + $packLeft;
                        $updates['pack_transfer'] = $packLeft;
                    }
                    $user->update($updates);
                    $count++;
                }
            });

        $this->info("已刷新 {$count} 个会员的流量配额");

        return self::SUCCESS;
    }
}
