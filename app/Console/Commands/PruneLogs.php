<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\CrashLog;
use App\Models\DeployRun;
use App\Models\DailyTraffic;
use App\Models\LoginLog;
use App\Models\NodeDailyTraffic;
use App\Models\NodeNetTraffic;
use App\Models\RuleTraffic;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

// 日志/统计保留策略:这些表只增不减,跑久了磁盘涨满、查询变慢。按保留天数分批删旧行。
// 分批(每批5000)删,避免大表单条 DELETE 长时间锁表。
class PruneLogs extends Command
{
    protected $signature = 'logs:prune
        {--login-days=90 : 登录日志保留天数}
        {--crash-days=180 : 崩溃日志保留天数}
        {--traffic-days=365 : 日流量/节点日流量保留天数}
        {--deploy-days=90 : 部署日志正文保留天数(行本身永久保留,见下)}
        {--audit-days=180 : 操作日志保留天数(登录失败保留 2 倍,见下)}';

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

        // 中转侧的按天统计表,同样只增不减(ADR-008 并入时 relaypanel 没有任何清理任务)。
        $e = $this->pruneBatched(RuleTraffic::where('date', '<', $trafficCut));
        $f = $this->pruneBatched(NodeNetTraffic::where('date', '<', $trafficCut));

        // [!!] 部署记录【只清日志正文,不删行】。deploy_runs.host_key 是 SSH 主机指纹的
        // TOFU 链:下次部署同一台会跟"该节点最近一条有指纹的记录"比对,不一致就告警
        // (见 Console\Commands\DeployRun)。把旧行删了,一台久未部署的机器再部署时
        // 就【比不出指纹变化】—— 换机/中间人的信号会静默消失。占地方的是 longtext 日志,
        // 那才是要清的东西。
        // 操作日志。[!] 由 relaypanel 的 relay:prune-audit 并入(ADR-008) ——
        // 那条命令挂在【宿主 cron】上而不是 Laravel 调度里,所以搬家时按
        // routes/console.php 对表根本看不见它,而 91vpn 这边从来不清 audit_logs。
        // 中转规则的改动现在也往这张表写,不清会一直涨。
        //
        // [!] 保留期比备份长(默认 180 天):查"这条规则半年前是谁改的"是操作
        // 日志的典型用途,而备份只需要能恢复到最近状态。
        //
        // [!!] 登录失败的记录【保留 2 倍期限】:它是唯一能看出"有人在长期试探"
        // 的信号,而那种试探本来就是慢的 —— 按同一个期限清掉,恰好把最该留的
        // 那部分先清了。
        $auditDays = max(7, (int) $this->option('audit-days'));
        $h = $this->pruneBatched(AuditLog::where('created_at', '<', now()->subDays($auditDays))
            ->where('action', '!=', 'login.fail'));
        $i = $this->pruneBatched(AuditLog::where('created_at', '<', now()->subDays($auditDays * 2))
            ->where('action', 'login.fail'));

        $g = DeployRun::whereNotNull('log')
            ->where('created_at', '<', now()->subDays((int) $this->option('deploy-days')))
            ->update(['log' => null]);

        $this->info("已清理:登录日志 {$a}、崩溃日志 {$b}、日流量 {$c}、节点日流量 {$d}、规则流量 {$e}、整机流量 {$f}、操作日志 {$h}(登录失败 {$i});清空部署日志正文 {$g} 条(行保留)");

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
