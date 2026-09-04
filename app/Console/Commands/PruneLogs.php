<?php

namespace App\Console\Commands;

use App\Models\CrashLog;
use App\Models\DailyTraffic;
use App\Models\LoginLog;
use App\Models\NodeDailyTraffic;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

// 日志/统计保留策略:这些表只增不减,跑久了磁盘涨满、查询变慢。按保留天数分批删旧行。
// 分批(每批5000)删,避免大表单条 DELETE 长时间锁表。
class PruneLogs extends Command
{
    protected $signature = 'logs:prune
        {--login-days=90 : 登录日志保留天数}
        {--crash-days=180 : 崩溃日志保留天数}
        {--traffic-days=365 : 日流量/节点日流量保留天数}';

    protected $description = '按保留天数清理日志/统计表(登录/崩溃/日流量),防磁盘无限增长';

    public function handle(): int
    {
        $loginCut = now()->subDays((int) $this->option('login-days'));
        $crashCut = now()->subDays((int) $this->option('crash-days'));
        $trafficCut = now()->subDays((int) $this->option('traffic-days'))->toDateString();

        $a = $this->pruneBatched(LoginLog::where('created_at', '<', $loginCut));
        $b = $this->pruneBatched(CrashLog::where('created_at', '<', $crashCut));
        $c = $this->pruneBatched(DailyTraffic::where('date', '<', $trafficCut));
        $d = $this->pruneBatched(NodeDailyTraffic::where('date', '<', $trafficCut));

        $this->info("已清理:登录日志 {$a}、崩溃日志 {$b}、日流量 {$c}、节点日流量 {$d}");

        return self::SUCCESS;
    }

    /** 分批删,防长锁 */
    private function pruneBatched(Builder $query, int $chunk = 5000): int
    {
        $total = 0;
        do {
            $n = (clone $query)->limit($chunk)->delete();
            $total += $n;
        } while ($n > 0);

        return $total;
    }
}
