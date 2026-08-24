<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetMonthlyTraffic extends Command
{
    protected $signature = 'traffic:reset-monthly';

    protected $description = '每月刷新有效会员的流量配额：清零已用 u/d（transfer_enable 月配额不变）';

    public function handle(): int
    {
        $affected = User::query()
            ->where('class', '>', 0)
            ->whereNotNull('class_expire')
            ->where('class_expire', '>', now())
            ->where(fn ($q) => $q->where('u', '>', 0)->orWhere('d', '>', 0))
            ->update(['u' => 0, 'd' => 0]);

        $this->info("已刷新 {$affected} 个会员的流量配额");

        return self::SUCCESS;
    }
}
