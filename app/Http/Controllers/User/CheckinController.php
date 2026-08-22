<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class CheckinController extends Controller
{
    /** POST /user/checkin —— 每日签到领流量 */
    public function store()
    {
        $user = auth()->user();

        // 同一天只能签到一次
        $lastDate = $user->last_check_in > 0
            ? \Illuminate\Support\Carbon::createFromTimestamp($user->last_check_in)->toDateString()
            : null;

        if ($lastDate === now()->toDateString()) {
            return back()->with('status', '今天已经签到过了');
        }

        // 随机奖励 100–500 MB
        $rewardBytes = random_int(100, 500) * 1024 * 1024;
        $user->increment('transfer_enable', $rewardBytes);
        $user->update(['last_check_in' => now()->timestamp]);

        return back()->with('status', '签到成功，获得 '.round($rewardBytes / 1024 / 1024).' MB 流量');
    }
}
