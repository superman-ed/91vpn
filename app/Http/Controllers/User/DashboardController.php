<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DailyTraffic;
use App\Support\ClientLinks;

class DashboardController extends Controller
{
    /** GET /user */
    public function index()
    {
        $user = auth()->user();

        $used = $user->usedTraffic();
        $remainBytes = max(0, $user->transfer_enable - $used);

        // 近7天流量
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $rows = DailyTraffic::where('user_id', $user->id)
            ->whereIn('date', $days->all())->get()->keyBy(fn ($r) => $r->date->toDateString());
        $chart = $days->map(fn ($d) => [
            'date' => substr($d, 5),   // MM-DD
            'gb' => round(((($rows[$d]->u ?? 0) + ($rows[$d]->d ?? 0)) / (1024 ** 3)), 2),
        ]);

        $links = ClientLinks::for($user);

        return view('user.dashboard', [
            'user' => $user,
            'usedGb' => bytes_to_gb($used),
            'remainGb' => bytes_to_gb($remainBytes),
            'totalGb' => bytes_to_gb($user->transfer_enable),
            'todayGb' => bytes_to_gb($user->transfer_today),
            'rebateTotal' => $user->paybacks()->sum('amount'),
            'membership' => $user->membershipText(),
            'className' => class_name($user->class),
            'expireDate' => $user->class > 0 && $user->class_expire ? $user->class_expire->format('Y-m-d H:i') : null,
            'usagePercent' => $user->usagePercent(),
            'checkedIn' => $user->checkedInToday(),
            'chart' => $chart,
            'subUrl' => $links['subUrl'],
            'clashScheme' => $links['clashScheme'],
        ]);
    }
}
