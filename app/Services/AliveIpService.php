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
     *
     * 策略：**保留最近还在活动的**，踢掉最久没动静的。
     *
     * `[!!]` 此前是按 `id` 升序「先到先得」，而 `id` 记的是**第一次见到这个 IP**，
     * 不是「它还在不在用」。后果是踢错人：
     * `[D]` 一台手机从 Wi-Fi 切到蜂窝会留下两行 —— 旧 IP 的 id 更小被保留，
     * 而用户**此刻真正在用**的那个新 IP 被踢掉。真机撞到过（alive_ips 里
     * id=35 的旧 IP 已 290 秒没动，id=36 是 1 秒前的当前连接）。
     * 手机进出 Wi-Fi 是每天很多次的事,这个误伤发生频率不低。
     *
     * `[!]` 这【不解决】"量的是 IP 不是设备"这个根本问题（见清单 L-08）——
     * 家里三台设备共用出口仍然只算 1 个，手机切网仍然多占 1 个。
     * 它只把伤害从「踢掉你正在用的」降到「踢掉你确实不在用的」。
     * 真正的修法是按设备发凭据，那是另一件事。
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
                // `[!]` 主序 last_seen 倒序：最近还在活动的优先保留 —— 这是本次修的。
                //
                // `[!!]` 次序 id 【升序】，不是降序。两者活跃度相同时（同一批上报，
                //   last_seen 完全一样），该踢的是【刚要建立的那条】，而不是已经
                //   跑了很久的那条 —— 拒绝新连接比掐断老连接对用户温和得多。
                //   一开始写成 id 降序，被 IpLimitTest「一次报 3 个 IP、限 2」
                //   那条用例当场抓到：它期望踢 3.3.3.3，而降序会去踢 1.1.1.1。
                ->orderByDesc('last_seen')
                ->orderBy('id')
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
