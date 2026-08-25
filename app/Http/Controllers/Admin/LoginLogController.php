<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    /** GET /admin/system/login-logs —— 登录日志(IP/UA/时间) */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $base = LoginLog::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('ip', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))))
            ->when($from, fn ($query) => $query->whereDate('logged_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('logged_at', '<=', $to));

        return view('admin.system.login-logs', [
            'logs' => (clone $base)->with('user')->latest('logged_at')->paginate(30)->withQueryString(),
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'total' => (clone $base)->count(),
            'todayCount' => (clone $base)->whereDate('logged_at', today())->count(),
            'uniqueIps' => (clone $base)->distinct('ip')->count('ip'),
        ]);
    }
}
