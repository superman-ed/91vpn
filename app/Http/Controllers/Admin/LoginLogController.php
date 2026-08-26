<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginLogController extends Controller
{
    /** 暴破告警阈值：近 WINDOW 小时内同 IP 失败达 THRESHOLD 次 */
    private const BF_WINDOW_HOURS = 24;
    private const BF_THRESHOLD = 5;

    /** GET /admin/system/login-logs —— 登录日志（成功/失败 + 暴破告警） */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $status = $request->query('status');
        $from = $request->query('from');
        $to = $request->query('to');

        $base = LoginLog::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('ip', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))))
            ->when(in_array($status, ['success', 'failed'], true), fn ($query) => $query->where('status', $status))
            ->dateBetween($from, $to, 'logged_at');

        $countBase = LoginLog::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('ip', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))))
            ->dateBetween($from, $to, 'logged_at');

        // 暴破告警：近 24h 内失败次数达阈值的 IP
        $alerts = LoginLog::where('status', 'failed')
            ->where('logged_at', '>=', now()->subHours(self::BF_WINDOW_HOURS))
            ->select('ip', DB::raw('count(*) as fails'), DB::raw('count(distinct email) as targets'), DB::raw('max(logged_at) as last_at'))
            ->groupBy('ip')
            ->havingRaw('count(*) >= ?', [self::BF_THRESHOLD])
            ->orderByDesc('fails')->get();

        return view('admin.system.login-logs', [
            'logs' => $base->with('user')->latest('logged_at')->paginate(30)->withQueryString(),
            'q' => $q,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'counts' => [
                'all' => (clone $countBase)->count(),
                'success' => (clone $countBase)->where('status', 'success')->count(),
                'failed' => (clone $countBase)->where('status', 'failed')->count(),
            ],
            'alerts' => $alerts,
            'bfWindow' => self::BF_WINDOW_HOURS,
            'bfThreshold' => self::BF_THRESHOLD,
        ]);
    }
}
