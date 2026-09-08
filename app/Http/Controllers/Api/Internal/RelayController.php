<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;

/**
 * 面板间内部只读 API —— 供 relaypanel 做「send_proxy ↔ accept_proxy」配对校验(方案 X)。
 *
 * 为什么在这:转发规则(带 send_proxy)在 relaypanel,而落地【实际生效】的 accept_proxy
 * 由落地节点上报到 91vpn(nodeHeartbeat 存 reported_accept_proxy)。relaypanel 校验时
 * 拿它转发目标的地址来这查落地真实状态,错开就标红。
 *
 * 鉴权:共享静态 token(RELAY_INTERNAL_TOKEN,两侧 .env 一致),hash_equals 比对;
 * 认不出一律 404(不暴露端点存在,同 mod_mu 的处理)。只读、不改任何数据。
 */
class RelayController extends Controller
{
    public function acceptProxy(Request $request)
    {
        $token = (string) config('services.relay_internal_token');
        $given = (string) ($request->header('X-Internal-Token') ?: $request->query('token', ''));
        if ($token === '' || ! hash_equals($token, $given)) {
            abort(404);
        }

        // servers=1.2.3.4,5.6.7.8 —— relaypanel 传它转发规则的落地目标地址(按 nodes.server 匹配)
        $servers = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('servers', '')))));
        $out = [];
        if ($servers !== []) {
            foreach (Node::whereIn('server', $servers)->get() as $n) {
                $out[$n->server] = [
                    'expected' => (bool) $n->accept_proxy_protocol,                                  // 面板配的期望值
                    'reported' => is_null($n->reported_accept_proxy) ? null : (bool) $n->reported_accept_proxy, // 节点实际在跑的;null=从没上报
                    'reported_at' => $n->accept_proxy_reported_at?->toIso8601String(),
                ];
            }
        }

        return response()->json(['ret' => 1, 'data' => $out]);
    }
}
