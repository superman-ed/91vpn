<?php

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\Request;

class NodeSecret
{
    public function handle(Request $request, Closure $next)
    {
        $nodeId = $request->query('node_id');
        $key = $request->query('key');

        $node = $nodeId ? Node::find($nodeId) : null;
        if ($node === null || ! hash_equals($node->secret, (string) $key)) {
            return response()->json(['ret' => 0, 'msg' => 'unauthorized'], 401);
        }

        // 把节点注入请求，控制器直接取用
        $request->attributes->set('node', $node);

        return $next($request);
    }
}
