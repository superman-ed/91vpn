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
                    $quotaBefore = (int) $user->transfer_enable;
                    $quotaAfter = (int) ($updates['transfer_enable'] ?? $quotaBefore);
                    $usedBefore = (int) $user->u + (int) $user->d;
                    $user->update($updates);
                    $count++;

                    // `[!!]` 只在【确实拿走了东西】时记一条 —— 也就是流量包被清掉的那一次。
                    //
                    // 不记全部刷新:每月每个会员一行,人工操作会被淹没,而人只会翻最上面那一屏。
                    // 而清掉流量包是【用户付过钱的东西消失了】,是唯一会变成争议的那一种,
                    // 事后要答得上"哪一次、抹掉了多少"。规则本身已在结账页告知(见 L-04),
                    // 这里补的是事后可追溯(L-09)。
                    if ($quotaAfter < $quotaBefore) {
                        $lost = $quotaBefore - $quotaAfter;
                        $remaining = max(0, $quotaBefore - $usedBefore);
                        system_audit('user.traffic_reset', sprintf(
                            '%s 流量重置：配额 %s → %s，其中流量包 %s 按规则清零（重置前剩余 %s，已用 %s 归零）',
                            $user->ident(), human_bytes($quotaBefore), human_bytes($quotaAfter),
                            human_bytes($lost), human_bytes($remaining), human_bytes($usedBefore),
                        ), $user);
                    }
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

                    // `[!]` 这一支【不写审计】:它只把已用清零(等于把额度还给用户),
                    // 不动 transfer_enable,没有任何东西被拿走。
                    // 记下来只会是每月每个免费用户一行的噪声。
                    $user->update(['u' => 0, 'd' => 0, 'next_reset_at' => $next]);
                    $freeCount++;
                }
            });

        $this->info("已刷新 {$count} 个会员 + {$freeCount} 个免费用户的流量配额");

        return self::SUCCESS;
    }
}
