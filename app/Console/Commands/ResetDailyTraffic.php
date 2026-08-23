<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetDailyTraffic extends Command
{
    protected $signature = 'traffic:reset-daily';

    protected $description = '每日 0 点清零所有用户的 transfer_today（今日已用流量）';

    public function handle(): int
    {
        $affected = User::query()->where('transfer_today', '>', 0)->update(['transfer_today' => 0]);

        $this->info("已重置 {$affected} 个用户的今日流量");

        return self::SUCCESS;
    }
}
