<?php

namespace App\Http\Controllers\Api\ModMu;

use App\Http\Controllers\Controller;
use App\Services\NodeUserService;
use App\Services\TrafficService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /** GET /mod_mu/users —— 节点拉取可服务用户名单 */
    public function index(Request $request, NodeUserService $service)
    {
        $node = $request->attributes->get('node');

        return response()->json([
            'ret' => 1,
            'users' => $service->servableUsers($node),
        ]);
    }

    /** POST /mod_mu/users/traffic —— 节点上报流量 */
    public function addTraffic(Request $request, TrafficService $service)
    {
        $node = $request->attributes->get('node');
        $logs = $request->input('data', []);

        $count = $service->record($node, is_array($logs) ? $logs : []);

        return response()->json(['ret' => 1, 'count' => $count]);
    }

    /** GET /mod_mu/func/ping —— 节点心跳 */
    public function ping(Request $request)
    {
        $node = $request->attributes->get('node');
        $node->update(['online' => true, 'last_heartbeat' => now()->timestamp]);

        return response()->json(['ret' => 1]);
    }
}
