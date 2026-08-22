<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /** GET /user */
    public function index()
    {
        $user = auth()->user();

        $used = $user->usedTraffic();
        $remainBytes = max(0, $user->transfer_enable - $used);

        return view('user.dashboard', [
            'user' => $user,
            'usedGb' => bytes_to_gb($used),
            'remainGb' => bytes_to_gb($remainBytes),
            'totalGb' => bytes_to_gb($user->transfer_enable),
            'todayGb' => bytes_to_gb($user->transfer_today),
            'className' => class_name($user->class),
        ]);
    }
}
