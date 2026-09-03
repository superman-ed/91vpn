<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;

class ServerApiController extends Controller
{
    /** GET /api/servers —— 当前用户可连的节点列表(按等级过滤,与网页版一致) */
    public function index(Request $request)
    {
        // 会员看自身等级内全部节点;非会员/过期用户只看免费节点(node_class=0),
        // 与订阅下发(SubscriptionService)和节点侧鉴权口径一致,避免列出连不上的付费节点。
        $user = $request->user();
        $maxClass = $user->hasActivePackage() ? $user->class : 0;

        $nodes = Node::where('online', true)
            ->where('node_class', '<=', $maxClass)
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
            ])->values();

        return response()->json(['ret' => 1, 'data' => $nodes]);
    }
}
