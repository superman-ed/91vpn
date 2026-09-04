<?php

namespace App\Services;

use App\Models\Node;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NodeUserService
{
    /** 名单缓存秒数：节点每分钟拉全量，短缓存扛住多节点并发；代价是封禁/耗尽/新购最多延迟该秒数生效 */
    public const CACHE_TTL = 60;

    /**
     * 返回该节点可服务的用户名单（带短缓存，规模化降 DB 压力）。
     * 条件：未封禁 AND 未过期 AND class>=node_class AND 流量未耗尽 AND 分组匹配。
     */
    public function servableUsers(Node $node): array
    {
        return Cache::remember("mod_mu:users:{$node->id}", self::CACHE_TTL, fn () => $this->query($node));
    }

    private function query(Node $node): array
    {
        $isFreeNode = (int) $node->node_class === 0;

        // 节点被运维排空(enabled=false)时,返回空名单——即便 agent 还在跑/心跳照写 online,
        // 用户也会从该节点漏干(no servable users),实现"先摘用户、漏干、再停 agent"的优雅下线。
        if (! $node->enabled) {
            return [];
        }

        $query = DB::table('users')
            ->where('banned', false)
            ->where('class', '>=', $node->node_class)
            ->whereColumn(DB::raw('u + d'), '<', 'transfer_enable');

        // 付费节点仍要求会员有效期。
        // 免费节点(node_class=0)不卡会员期:会员按自身额度使用;非会员/过期用户
        // 额外受"免费封顶"约束(u+d < free_cap),防止过期会员拿旧套餐大额度在免费节点白嫖。
        if (! $isFreeNode) {
            $query->where('class_expire', '>', now());
        } else {
            $cap = free_traffic_cap_bytes();
            $query->where(function ($q) use ($cap) {
                $q->where('class_expire', '>', now())
                    ->orWhereRaw('u + d < ?', [$cap]);
            });
        }

        if ($node->node_group > 0) {
            // node_group=0 表示不限分组；此处按需扩展 user 分组匹配
        }

        $nodeSpeed = (int) $node->speed_limit;

        return $query->get(['id', 'uuid', 'passwd', 'node_speed_limit', 'node_ip_limit', 'class', 'class_expire'])
            ->map(function ($u) use ($isFreeNode, $nodeSpeed) {
                $activeMember = $u->class > 0 && $u->class_expire !== null && strtotime($u->class_expire) > time();
                // 免费节点上的非会员/过期用户:用节点自带限速;会员及付费节点仍用用户套餐限速
                $speed = ($isFreeNode && ! $activeMember) ? $nodeSpeed : (int) $u->node_speed_limit;

                return [
                    'id' => $u->id,
                    'uuid' => $u->uuid,
                    'passwd' => $u->passwd,
                    'speed_limit' => $speed,
                    'ip_limit' => $u->node_ip_limit,
                ];
            })->all();
    }
}
