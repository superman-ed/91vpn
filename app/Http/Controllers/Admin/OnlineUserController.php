<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AliveIp;
use App\Models\User;
use Illuminate\Http\Request;

class OnlineUserController extends Controller
{
    /** GET /admin/online —— 当前在线用户及其流量情况（基于节点上报的 alive_ips） */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $window = now()->subSeconds(AliveIp::ONLINE_WINDOW);

        // 窗口内有上报的用户即“在线”
        $aliveUserIds = AliveIp::where('last_seen', '>=', $window)->distinct()->pluck('user_id');

        $users = User::whereIn('id', $aliveUserIds)
            ->when($q, fn ($query) => $query->where('email', 'like', "%{$q}%"))
            ->orderByDesc('transfer_today')
            ->paginate(30)->withQueryString();

        // 当前页用户的在线 IP / 节点明细
        $alive = AliveIp::where('last_seen', '>=', $window)
            ->whereIn('user_id', $users->pluck('id'))
            ->with('node:id,name')
            ->get()
            ->groupBy('user_id');

        return view('admin.online.index', [
            'users' => $users,
            'alive' => $alive,
            'q' => $q,
            'onlineUsers' => $aliveUserIds->count(),
            'onlineDevices' => AliveIp::where('last_seen', '>=', $window)->distinct('ip')->count('ip'),
            'todayTraffic' => (int) User::whereIn('id', $aliveUserIds)->sum('transfer_today'),
            'windowSec' => AliveIp::ONLINE_WINDOW,
        ]);
    }
}
