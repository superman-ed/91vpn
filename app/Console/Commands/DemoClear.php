<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoClear extends Command
{
    protected $signature = 'demo:clear {--force : 跳过确认}';

    protected $description = '清空所有演示 mock 数据，回到纯真实空状态（不删除用户账号本身）';

    /** 直接清空的日志/统计表 */
    private const TABLES = [
        'daily_stats' => '在线/日活统计',
        'node_daily_traffic' => '节点流量',
        'email_logs' => '邮件记录',
        'login_logs' => '登录日志',
        'subscribe_logs' => '订阅/设备记录',
        'audit_logs' => '操作日志',
    ];

    public function handle(): int
    {
        $counts = [];
        foreach (self::TABLES as $table => $label) {
            $counts[$table] = DB::table($table)->count();
        }
        $usersWithRuntime = DB::table('users')
            ->where(fn ($q) => $q->where('u', '>', 0)->orWhere('d', '>', 0)
                ->orWhere('transfer_today', '>', 0)->orWhereNotNull('last_used_at')
                ->orWhereNotNull('reg_referer'))
            ->count();
        $total = array_sum($counts) + $usersWithRuntime;

        if ($total === 0) {
            $this->info('没有可清理的演示数据。');

            return self::SUCCESS;
        }

        $this->line('将清理：');
        foreach (self::TABLES as $table => $label) {
            $this->line("  - {$label}（{$table}）：{$counts[$table]} 行");
        }
        $this->line("  - 重置用户运行时字段（已用流量/今日流量/最后活跃/注册来源）：{$usersWithRuntime} 个用户");

        if (! $this->option('force') && ! $this->confirm('确认清理？（不会删除用户账号本身）')) {
            $this->warn('已取消。');

            return self::SUCCESS;
        }

        foreach (array_keys(self::TABLES) as $table) {
            DB::table($table)->delete();
        }
        DB::table('users')->update([
            'u' => 0, 'd' => 0, 'transfer_today' => 0,
            'last_used_at' => null, 'reg_ip' => null, 'reg_referer' => null,
        ]);

        $this->info('演示数据已清空，页面回到真实空状态。真实数据将由节点上报 / 用户行为重新累积。');

        return self::SUCCESS;
    }
}
