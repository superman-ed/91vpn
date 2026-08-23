<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SubscribeLog;

class SubscribeLogController extends Controller
{
    /** GET /user/subscribe-log */
    public function index()
    {
        $logs = SubscribeLog::where('user_id', auth()->id())
            ->latest('fetched_at')->limit(30)->get();

        return view('user.subscribe-log', ['logs' => $logs]);
    }
}
