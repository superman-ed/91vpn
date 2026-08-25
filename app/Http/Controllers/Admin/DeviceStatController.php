<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceStatController extends Controller
{
    /** GET /admin/system/devices —— 安装设备统计（自研客户端上报的真实设备） */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $platform = $request->query('platform');
        $from = $request->query('from');
        $to = $request->query('to');

        $base = Device::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('model', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))))
            ->when($platform, fn ($query) => $query->where('platform', $platform))
            ->when($from, fn ($query) => $query->whereDate('last_seen', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('last_seen', '<=', $to));

        $dist = fn ($col, $q) => (clone $q)->whereNotNull($col)->where($col, '!=', '')
            ->select($col, DB::raw('count(*) as c'))->groupBy($col)->orderByDesc('c')->pluck('c', $col);

        return view('admin.system.devices', [
            'devices' => (clone $base)->with('user')->latest('last_seen')->paginate(30)->withQueryString(),
            'q' => $q,
            'platform' => $platform,
            'from' => $from,
            'to' => $to,
            'deviceCount' => (clone $base)->count(),
            'userCount' => (clone $base)->distinct('user_id')->count('user_id'),
            'onlineCount' => (clone $base)->where('last_seen', '>=', now()->subSeconds(Device::ONLINE_WINDOW))->count(),
            'byPlatform' => $dist('platform', $base),
            'byModel' => $dist('model', $base)->take(10),
            'byOsVersion' => $dist('os_version', $base)->take(10),
            'byAppVersion' => $dist('app_version', $base),
        ]);
    }
}
