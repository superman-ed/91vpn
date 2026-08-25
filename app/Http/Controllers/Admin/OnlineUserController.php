<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AliveIp;
use App\Models\DailyStat;
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

        // 近 30 日趋势（无快照的日期补 0）
        $days = 30;
        $stats = DailyStat::where('date', '>=', today()->subDays($days - 1))->get()->keyBy(fn ($s) => $s->date->toDateString());
        $traffic = \App\Models\NodeDailyTraffic::where('date', '>=', today()->subDays($days - 1))
            ->selectRaw('date, sum(u + d) as raw')->groupBy('date')->get()->keyBy(fn ($s) => $s->date->toDateString());
        $trend = collect();
        for ($d = today()->subDays($days - 1); $d->lte(today()); $d->addDay()) {
            $row = $stats->get($d->toDateString());
            $trend->push([
                'label' => $d->format('m-d'),
                'dau' => (int) ($row->dau ?? 0),
                'peak' => (int) ($row->peak_online ?? 0),
                'traffic' => (int) ($traffic->get($d->toDateString())->raw ?? 0),
            ]);
        }

        return view('admin.online.index', [
            'users' => $users,
            'alive' => $alive,
            'q' => $q,
            'onlineUsers' => $aliveUserIds->count(),
            'onlineDevices' => AliveIp::where('last_seen', '>=', $window)->distinct('ip')->count('ip'),
            'todayTraffic' => (int) User::whereIn('id', $aliveUserIds)->sum('transfer_today'),
            'windowSec' => AliveIp::ONLINE_WINDOW,
            'trend' => $trend,
            'trendMax' => max(1, (int) $trend->max('dau'), (int) $trend->max('peak')),
        ]);
    }
}
