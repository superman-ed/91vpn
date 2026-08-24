<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DailyTraffic;

class TrafficLogController extends Controller
{
    /** GET /user/traffic */
    public function index()
    {
        $records = DailyTraffic::where('user_id', auth()->id())
            ->orderByDesc('date')->limit(30)->get();

        return view('user.traffic', [
            'records' => $records,                              // 表格用（倒序）
            'chart' => $records->sortBy('date')->values(),      // 柱状图用（正序）
            'total' => $records->sum(fn ($r) => $r->u + $r->d),
            'maxDay' => (int) $records->max(fn ($r) => $r->u + $r->d) ?: 1,
        ]);
    }
}
