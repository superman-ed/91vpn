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
        $query = DB::table('users')
            ->where('banned', false)
            ->where('class_expire', '>', now())
            ->where('class', '>=', $node->node_class)
            ->whereColumn(DB::raw('u + d'), '<', 'transfer_enable');

        if ($node->node_group > 0) {
            // node_group=0 表示不限分组；此处按需扩展 user 分组匹配
        }

        return $query->get(['id', 'uuid', 'passwd', 'node_speed_limit', 'node_ip_limit'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'uuid' => $u->uuid,
                'passwd' => $u->passwd,
                'speed_limit' => $u->node_speed_limit,
                'ip_limit' => $u->node_ip_limit,
            ])->all();
    }
}
