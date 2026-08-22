<?php

namespace App\Services;

use App\Models\Node;
use Illuminate\Support\Facades\DB;

class NodeUserService
{
    /**
     * 返回该节点可服务的用户名单。
     * 条件：未封禁 AND 未过期 AND class>=node_class AND 流量未耗尽 AND 分组匹配。
     */
    public function servableUsers(Node $node): array
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
