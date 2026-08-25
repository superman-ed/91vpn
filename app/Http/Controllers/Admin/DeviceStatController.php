<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscribeLog;
use Illuminate\Http\Request;

class DeviceStatController extends Controller
{
    /** GET /admin/system/devices —— 客户端/设备统计(基于订阅拉取 UA) */
    public function index(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $base = SubscribeLog::query()
            ->when($from, fn ($query) => $query->whereDate('fetched_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('fetched_at', '<=', $to));

        // 每用户取最近一次拉取的 UA → 归类 → 统计"用某客户端的用户数"
        $latest = (clone $base)->orderByDesc('fetched_at')->get(['user_id', 'client'])
            ->unique('user_id');
        $byFamily = $latest->groupBy(fn ($r) => client_family($r->client))
            ->map->count()->sortDesc();

        return view('admin.system.devices', [
            'byFamily' => $byFamily,
            'totalUsers' => $latest->count(),
            'totalFetches' => (clone $base)->count(),
            'recent' => (clone $base)->with('user')->latest('fetched_at')->paginate(20)->withQueryString(),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
