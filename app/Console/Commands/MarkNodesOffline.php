<?php

namespace App\Console\Commands;

use App\Models\Node;
use Illuminate\Console\Command;

// 死节点自动离线:online 只被心跳置 true,若无此任务,agent 崩/停后 online 永远 true,
// 死节点仍进订阅/列表 → 用户拿到连不上的节点。此任务把"曾上线又心跳失联"的节点置离线。
// 注:last_heartbeat=0(从未连过)的节点不动——那是新建/未接 agent,由 enabled 控制是否服务。
class MarkNodesOffline extends Command
{
    protected $signature = 'nodes:mark-offline {--seconds=180 : 心跳超过多少秒未更新即判离线}';

    protected $description = '把心跳失联的节点置为离线(online=false),避免死节点仍被下发给用户';

    public function handle(): int
    {
        $threshold = max(30, (int) $this->option('seconds'));
        $cutoff = now()->timestamp - $threshold;

        $n = Node::where('online', true)
            ->where('last_heartbeat', '>', 0)   // 只处理曾上线过的;从未心跳的(=0)不动
            ->where('last_heartbeat', '<', $cutoff)
            ->update(['online' => false]);

        $this->info("已将 {$n} 个心跳失联(>{$threshold}s)的节点置为离线");

        return self::SUCCESS;
    }
}
