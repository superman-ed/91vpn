<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Console\Command;

class SendExpiryReminders extends Command
{
    protected $signature = 'notify:expiry';

    protected $description = '给即将到期(3天内)的会员自动发到期提醒站内信，每人每次到期周期只发一次';

    public function handle(): int
    {
        $sent = 0;
        User::where('banned', false)->where('class', '>', 0)
            ->whereBetween('class_expire', [now(), now()->addDays(3)])
            ->chunkById(200, function ($users) use (&$sent) {
                foreach ($users as $u) {
                    // 去重：近 4 天内已给该用户发过到期提醒就跳过（避免每天重复轰炸；下个到期周期会再发）
                    $exists = UserNotification::where('user_id', $u->id)->where('type', 'expiry')
                        ->where('created_at', '>=', now()->subDays(4))->exists();
                    if ($exists) {
                        continue;
                    }
                    $days = (int) ceil(now()->floatDiffInDays($u->class_expire));
                    UserNotification::create([
                        'user_id' => $u->id, 'type' => 'expiry', 'pinned' => true,
                        'title' => '套餐即将到期提醒',
                        'content' => "您的套餐将于 {$u->class_expire->format('Y-m-d')} 到期（约 {$days} 天后）。为避免服务中断，请及时续费。",
                    ]);
                    $sent++;
                }
            });

        $this->info("已发送 {$sent} 条到期提醒");

        return self::SUCCESS;
    }
}
