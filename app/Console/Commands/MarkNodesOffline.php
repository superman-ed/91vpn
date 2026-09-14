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

        // `[!!]` 这里【刻意不写审计】,不是漏了。
        // 它每分钟跑一次,节点抖动时会反复翻转 —— 记下来会把人工操作淹掉,
        // 而人只会翻最上面那一屏。节点上下线已经由 node_health_spells
        // 按"存活区段"完整记录(见 HealthSampler),那才是该查的地方。
        // 审计日志记的是【会变成争议的业务状态】,不是运维遥测。
        $this->info("已将 {$n} 个心跳失联(>{$threshold}s)的节点置为离线");

        return self::SUCCESS;
    }
}
