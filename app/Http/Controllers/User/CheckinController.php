<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckinController extends Controller
{
    /** POST /user/checkin —— 每日签到领流量 */
    public function store()
    {
        $user = auth()->user();
        $rewardBytes = random_int(100, 500) * 1024 * 1024;   // 随机奖励 100–500 MB
        $todayStart = now()->startOfDay()->timestamp;

        // 原子条件更新:仅当"上次签到在今日零点之前"才发放,受影响行数=0 即今天已签。
        // 避免读-判-写的并发窗口被脚本重复领取。
        $affected = User::whereKey($user->id)
            ->where(fn ($q) => $q->whereNull('last_check_in')->orWhere('last_check_in', '<', $todayStart))
            ->update([
                'transfer_enable' => DB::raw("transfer_enable + {$rewardBytes}"),
                'last_check_in' => now()->timestamp,
            ]);

        if ($affected === 0) {
            return back()->with('status', '今天已经签到过了');
        }

        return back()->with('status', '签到成功，获得 '.round($rewardBytes / 1024 / 1024).' MB 流量');
    }
}
