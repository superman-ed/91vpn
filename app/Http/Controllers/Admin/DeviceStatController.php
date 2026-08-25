<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
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

        // 每用户取最近一次拉取的 UA → 按「设备平台/系统」归类
        $latest = (clone $base)->orderByDesc('fetched_at')->get(['user_id', 'client'])
            ->unique('user_id');
        $byPlatform = $latest->groupBy(fn ($r) => device_platform($r->client))
            ->map->count()->sortDesc();

        // 自研客户端上报的精确设备（devices 表；框架先行，等客户端接入后有数据）
        $devicesQ = Device::query()
            ->when($from, fn ($q) => $q->whereDate('last_seen', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('last_seen', '<=', $to));
        $count = fn ($col) => (clone $devicesQ)->whereNotNull($col)->where($col, '!=', '')
            ->select($col, \DB::raw('count(*) as c'))->groupBy($col)->orderByDesc('c')->pluck('c', $col);

        return view('admin.system.devices', [
            'byPlatform' => $byPlatform,
            'totalUsers' => $latest->count(),
            'totalFetches' => (clone $base)->count(),
            'recent' => (clone $base)->with('user')->latest('fetched_at')->paginate(20)->withQueryString(),
            'from' => $from,
            'to' => $to,
            // 自研客户端精确设备
            'deviceCount' => (clone $devicesQ)->count(),
            'deviceUserCount' => (clone $devicesQ)->distinct('user_id')->count('user_id'),
            'onlineDevices' => (clone $devicesQ)->where('last_seen', '>=', now()->subSeconds(Device::ONLINE_WINDOW))->count(),
            'byModel' => $count('model')->take(10),
            'byOsVersion' => $count('os_version')->take(10),
            'byAppVersion' => $count('app_version'),
        ]);
    }
}
