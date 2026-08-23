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

        return view('user.traffic', ['records' => $records]);
    }
}
