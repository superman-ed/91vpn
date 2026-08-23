<?php

namespace App\Services;

use App\Models\AliveIp;
use App\Models\Node;

class AliveIpService
{
    /**
     * 记录节点上报的在线 IP。每条 {user_id, ip} 刷新其 last_seen。
     *
     * @return int 成功记录的条数
     */
    public function record(Node $node, array $logs): int
    {
        $count = 0;

        foreach ($logs as $log) {
            $userId = $log['user_id'] ?? null;
            $ip = $log['ip'] ?? null;
            if (! $userId || ! $ip) {
                continue;
            }

            AliveIp::updateOrCreate(
                ['user_id' => $userId, 'ip' => $ip],
                ['node_id' => $node->id, 'last_seen' => now()],
            );
            $count++;
        }

        return $count;
    }
}
