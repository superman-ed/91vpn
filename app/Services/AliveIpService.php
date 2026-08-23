<?php

namespace App\Services;

use App\Models\AliveIp;
use App\Models\Node;
use App\Models\User;

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

    /**
     * 计算这些用户中超出设备上限、应被节点踢下线的 IP。
     * 策略：先到先得——按首次上报顺序保留 ip_limit 个，其余判定为超限。
     *
     * @param  array<int>  $userIds
     * @return array<int, array{user_id:int, ips:array<string>}>
     */
    public function blockedIps(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $window = now()->subSeconds(AliveIp::ONLINE_WINDOW);
        $out = [];

        $users = User::whereIn('id', $userIds)
            ->where('node_ip_limit', '>', 0)   // 0 = 不限
            ->get(['id', 'node_ip_limit']);

        foreach ($users as $user) {
            $ips = AliveIp::where('user_id', $user->id)
                ->where('last_seen', '>=', $window)
                ->orderBy('id')            // 首次上报者 id 更小，优先保留
                ->pluck('ip');

            if ($ips->count() > $user->node_ip_limit) {
                $out[] = [
                    'user_id' => $user->id,
                    'ips' => $ips->slice($user->node_ip_limit)->values()->all(),
                ];
            }
        }

        return $out;
    }
}
