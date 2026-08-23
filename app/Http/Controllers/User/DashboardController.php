<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\ClientLinks;

class DashboardController extends Controller
{
    /** GET /user */
    public function index()
    {
        $user = auth()->user();

        $used = $user->usedTraffic();
        $remainBytes = max(0, $user->transfer_enable - $used);

        // 最近公告
        $announcements = Announcement::where('published', true)
            ->orderByDesc('sort')->orderByDesc('created_at')->limit(5)->get();

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
            'announcements' => $announcements,
            'subUrl' => $links['subUrl'],
            'clashScheme' => $links['clashScheme'],
        ]);
    }
}
