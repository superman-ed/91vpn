<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountApiController extends Controller
{
    /** POST /api/checkin —— 每日签到领流量(原子条件更新,与网页版一致,防并发重复领) */
    public function checkin(Request $request)
    {
        $user = $request->user();
        $reward = random_int(100, 500) * 1024 * 1024;   // 100–500 MB
        $todayStart = now()->startOfDay()->timestamp;

        $affected = User::whereKey($user->id)
            ->where(fn ($q) => $q->whereNull('last_check_in')->orWhere('last_check_in', '<', $todayStart))
            ->update([
                'transfer_enable' => DB::raw("transfer_enable + {$reward}"),
                'last_check_in' => now()->timestamp,
            ]);

        if ($affected === 0) {
            return response()->json(['ret' => 0, 'msg' => '今天已经签到过了']);
        }

        return response()->json(['ret' => 1, 'data' => ['reward_mb' => (int) round($reward / 1024 / 1024)]]);
    }

    /** POST /api/account/password —— 修改登录密码 */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['ret' => 0, 'msg' => '当前密码错误'], 422);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['ret' => 1, 'msg' => '密码已修改']);
    }
}
