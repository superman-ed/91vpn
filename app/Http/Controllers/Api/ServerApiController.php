<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;

class ServerApiController extends Controller
{
    /** GET /api/servers —— 全量在线节点列表;超出当前可用等级的标记 locked=true(需订阅解锁) */
    public function index(Request $request)
    {
        // 全量展示(让用户看到节点丰富度);但超出当前等级的付费节点标 locked,
        // 客户端据此加锁、点击提示订阅。会员看到自身等级内为可用,其余 locked;
        // 非会员/过期(maxClass=0)只有免费节点 node_class=0 可用,其余 locked。
        $user = $request->user();
        $maxClass = $user->hasActivePackage() ? $user->class : 0;

        $nodes = Node::where('online', true)
            ->where('enabled', true)   // 排空/维护中的节点不给用户展示或选择
            ->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Node $n) => [
                'id' => $n->id,
                'name' => $n->name,
                'type' => $n->type,
                'net' => $n->net,
                'traffic_rate' => (float) $n->traffic_rate,
                'node_class' => (int) $n->node_class,
                'speed_limit' => (int) $n->speed_limit,   // 0 = 不限
                'online' => (bool) $n->online,
                'locked' => (int) $n->node_class > $maxClass,   // true=超出可用等级,需订阅解锁
            ])->values();

        return response()->json(['ret' => 1, 'data' => $nodes]);
    }
}
