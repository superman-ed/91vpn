<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AliveIp;
use App\Support\GeoIp;

class DeviceController extends Controller
{
    /** GET /user/devices —— 当前账号在线设备(按节点上报的 alive_ip 去重),用于发现异常登录 */
    public function index()
    {
        $user = auth()->user();
        $window = now()->subSeconds(AliveIp::ONLINE_WINDOW);

        $rows = $user->aliveIps()
            ->where('last_seen', '>=', $window)
            ->with('node')
            ->orderByDesc('last_seen')
            ->get()
            ->map(fn ($a) => [
                'ip' => $a->ip,
                'location' => GeoIp::locate($a->ip) ?: '未知归属地',
                'node' => $a->node?->name ?? '—',
                'last_seen' => $a->last_seen,
            ]);

        return view('user.devices', [
            'devices' => $rows,
            'limit' => (int) $user->node_ip_limit,   // 0 = 不限
            'onlineCount' => $rows->count(),
        ]);
    }
}
