<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrashLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrashLogController extends Controller
{
    /** GET /admin/system/crashes —— 崩溃日志(按 fingerprint 聚合:同一个 bug 归一条) */
    public function index(Request $request)
    {
        $platform = $request->query('platform');
        $appVersion = $request->query('app_version');
        $q = $request->query('q');

        $base = CrashLog::query()
            ->when($platform, fn ($x) => $x->where('platform', $platform))
            ->when($appVersion, fn ($x) => $x->where('app_version', $appVersion))
            ->when($q, fn ($x) => $x->where('message', 'like', "%{$q}%"));

        $groups = (clone $base)
            ->select('fingerprint',
                DB::raw('count(*) as c'),
                DB::raw('count(distinct user_id) as users'),
                DB::raw('max(message) as message'),
                DB::raw('max(platform) as platform'),
                DB::raw('max(app_version) as app_version'),
                DB::raw('max(created_at) as last_at'))
            ->groupBy('fingerprint')
            ->orderByDesc('last_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.system.crashes', [
            'groups' => $groups,
            'platform' => $platform,
            'appVersion' => $appVersion,
            'q' => $q,
            'total' => (clone $base)->count(),
            'last24h' => (clone $base)->where('created_at', '>=', now()->subDay())->count(),
            'kinds' => (clone $base)->distinct('fingerprint')->count('fingerprint'),
            'byAppVersion' => (clone $base)->whereNotNull('app_version')->where('app_version', '!=', '')
                ->select('app_version', DB::raw('count(*) as c'))->groupBy('app_version')->orderByDesc('c')->pluck('c', 'app_version'),
        ]);
    }

    /** GET /admin/system/crashes/{fingerprint} —— 某个 bug 的近期发生记录(含堆栈) */
    public function show(string $fingerprint)
    {
        $items = CrashLog::where('fingerprint', $fingerprint)
            ->with('user')
            ->latest()
            ->limit(50)
            ->get();

        abort_if($items->isEmpty(), 404);

        return view('admin.system.crash-detail', [
            'fingerprint' => $fingerprint,
            'items' => $items,
            'total' => CrashLog::where('fingerprint', $fingerprint)->count(),
        ]);
    }
}
