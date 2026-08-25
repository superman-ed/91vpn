<?php

namespace App\Console\Commands;

use App\Models\DailyStat;
use App\Models\NodeDailyTraffic;
use Illuminate\Console\Command;

class ClearDemoStats extends Command
{
    protected $signature = 'demo:clear-stats {--force : 跳过确认}';

    protected $description = '清空演示用的统计数据(daily_stats / node_daily_traffic)，回到纯真实状态';

    public function handle(): int
    {
        $ds = DailyStat::count();
        $nt = NodeDailyTraffic::count();

        if ($ds === 0 && $nt === 0) {
            $this->info('没有可清理的统计数据。');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("将清空 daily_stats({$ds} 行) 和 node_daily_traffic({$nt} 行)，确认？")) {
            $this->warn('已取消。');

            return self::SUCCESS;
        }

        DailyStat::truncate();
        NodeDailyTraffic::truncate();

        $this->info("已清空：daily_stats {$ds} 行、node_daily_traffic {$nt} 行。趋势/流量将从真实上报重新累积。");

        return self::SUCCESS;
    }
}
