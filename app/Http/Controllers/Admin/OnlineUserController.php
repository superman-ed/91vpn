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

        // 趋势区间（默认近 30 天，可选，最长 180 天）
        $to = $this->parseDate($request->query('to'), today());
        $from = $this->parseDate($request->query('from'), today()->copy()->subDays(29));
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 180) {
            $from = $to->copy()->subDays(180);
        }

        $stats = DailyStat::whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->keyBy(fn ($s) => $s->date->toDateString());
        $traffic = \App\Models\NodeDailyTraffic::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('date, sum(u + d) as raw')->groupBy('date')->get()->keyBy(fn ($s) => $s->date->toDateString());
        $trend = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $row = $stats->get($d->toDateString());
            $trend->push([
                'label' => $d->format('m-d'),
                'dau' => (int) ($row->dau ?? 0),
                'peak' => (int) ($row->peak_online ?? 0),
                'traffic' => (int) ($traffic->get($d->toDateString())->raw ?? 0),
            ]);
        }
        $rangeDays = (int) $from->diffInDays($to) + 1;

        return view('admin.online.index', [
            'users' => $users,
            'alive' => $alive,
            'q' => $q,
            'onlineUsers' => $aliveUserIds->count(),
            'onlineDevices' => AliveIp::where('last_seen', '>=', $window)->distinct('ip')->count('ip'),
            'onlineTodayTraffic' => (int) User::whereIn('id', $aliveUserIds)->sum('transfer_today'),
            'windowSec' => AliveIp::ONLINE_WINDOW,
            'trend' => $trend,
            'trendMax' => max(1, (int) $trend->max('dau'), (int) $trend->max('peak')),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rangeDays' => $rangeDays,
            // 区间统计（受日期选择影响）
            'rangeTraffic' => $rangeTraffic = (int) $trend->sum('traffic'),
            'rangeAvgTraffic' => (int) round($rangeTraffic / max(1, $rangeDays)),
            'peakDau' => (int) $trend->max('dau'),
            'peakOnline' => (int) $trend->max('peak'),
        ]);
    }

    private function parseDate(?string $value, \Illuminate\Support\Carbon $default): \Illuminate\Support\Carbon
    {
        if (! $value) {
            return $default;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
