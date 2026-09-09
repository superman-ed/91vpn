<?php

namespace App\Console\Commands;

use App\Models\AliveIp;
use App\Models\RuleAliveIp;
use Illuminate\Console\Command;

class PruneAliveIps extends Command
{
    protected $signature = 'alive-ips:prune
        {--relay-hours=24 : 中转在线 IP 保留小时数}';

    protected $description = '删除超出在线窗口的过期 alive_ips 记录';

    public function handle(): int
    {
        $deleted = AliveIp::where('last_seen', '<', now()->subSeconds(AliveIp::ONLINE_WINDOW))->delete();

        // 中转侧的同类表。[!!] 建表时就写了 index('last_seen') "清理过期用",
        // 但 relaypanel 从头到尾【没有任何定时任务】,这张表只增不减 ——
        // 每来一个新客户端 IP 就多一行,永不回收。并入 91vpn 后补上。
        // 保留窗口比页面展示窗口(STALE_MINUTES=15)宽得多:展示只看最近一刻,
        // 而排查"昨天谁在用这条中转"还需要行还在。
        $relayCut = now()->subHours((int) $this->option('relay-hours'));
        $relayDeleted = RuleAliveIp::where('last_seen', '<', $relayCut)->delete();

        $this->info("已清理 {$deleted} 条过期在线 IP 记录、{$relayDeleted} 条过期中转在线 IP 记录");

        return self::SUCCESS;
    }
}
