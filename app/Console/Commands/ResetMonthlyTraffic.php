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
                    // 额度归位到基础月配额：不结转，且清掉本周期买的加油包（老用户无基准则不动）
                    if ($user->base_transfer_enable > 0) {
                        $updates['transfer_enable'] = $user->base_transfer_enable;
                    }
                    $user->update($updates);
                    $count++;
                }
            });

        // 免费/过期用户:每月再生免费额度——仅清零已用 u/d(签到累积的 transfer_enable 保留,
        // 且节点侧已按 free_cap 封顶),让他们下个月又有免费额度可用。
        $freeCount = 0;
        User::query()
            ->whereNotNull('next_reset_at')
            ->where('next_reset_at', '<=', $now)
            ->where(fn ($q) => $q->where('class', '<=', 0)
                ->orWhereNull('class_expire')
                ->orWhere('class_expire', '<=', $now))
            ->chunkById(500, function ($users) use ($now, &$freeCount) {
                foreach ($users as $user) {
                    $next = $user->next_reset_at->copy();
                    do {
                        $next = $next->addMonthNoOverflow();
                    } while ($next->lte($now));

                    $user->update(['u' => 0, 'd' => 0, 'next_reset_at' => $next]);
                    $freeCount++;
                }
            });

        $this->info("已刷新 {$count} 个会员 + {$freeCount} 个免费用户的流量配额");

        return self::SUCCESS;
    }
}
