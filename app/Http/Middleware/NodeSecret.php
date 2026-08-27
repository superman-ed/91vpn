<?php

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\Request;

class NodeSecret
{
    public function handle(Request $request, Closure $next)
    {
        // node_id 来源:query(常规) → 请求头 → 路由段(XrayR 的 /nodes/{id}/info 把 id 放在路径里)
        $nodeId = $request->query('node_id') ?: $request->header('X-Node-Id') ?: $request->route('node');
        $key = $request->header('X-Node-Secret');
        if ($key === null && $request->query('key') !== null) {
            // 过渡兼容:仍接受 ?key=,但记录告警,提示尽快切到请求头
            $key = $request->query('key');
            \Illuminate\Support\Facades\Log::warning('节点鉴权仍走 query key,请升级 agent 改用 X-Node-Secret 头', ['node_id' => $nodeId]);
        }

        $node = $nodeId ? Node::find($nodeId) : null;
        if ($node === null || $key === null || ! hash_equals($node->secret, (string) $key)) {
            return response()->json(['ret' => 0, 'msg' => 'unauthorized'], 401);
        }

        // 把节点注入请求，控制器直接取用
        $request->attributes->set('node', $node);

        return $next($request);
    }
}
