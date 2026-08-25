<?php

namespace App\Console\Commands;

use App\Models\AliveIp;
use App\Models\DailyStat;
use App\Models\User;
use Illuminate\Console\Command;

class SnapshotDailyStats extends Command
{
    protected $signature = 'stats:snapshot';

    protected $description = '采样当日在线/日活并写入 daily_stats（在线峰值取当日最大，日活/新增取最新）';

    public function handle(): int
    {
        $today = today();

        // 当前在线（去重用户），用于累积当日峰值
        $currentOnline = AliveIp::where('last_seen', '>=', now()->subSeconds(AliveIp::ONLINE_WINDOW))
            ->distinct()->count('user_id');

        $dau = User::whereDate('last_used_at', $today)->count();
        $newUsers = User::whereDate('created_at', $today)->count();

        $stat = DailyStat::firstOrNew(['date' => $today->toDateString()]);
        $stat->dau = $dau;                                             // 刷新为当日最新
        $stat->new_users = $newUsers;
        $stat->peak_online = max((int) $stat->peak_online, $currentOnline);   // 峰值只增不减
        $stat->save();

        $this->info("快照 {$today->toDateString()}：在线 {$currentOnline}(峰值 {$stat->peak_online})/日活 {$dau}/新增 {$newUsers}");

        return self::SUCCESS;
    }
}
