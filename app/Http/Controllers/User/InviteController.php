<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Payback;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    /** GET /user/invite */
    public function index()
    {
        $user = auth()->user();

        // 兜底：老用户没有 ref_code 则补一个
        if (empty($user->ref_code)) {
            $user->update(['ref_code' => Str::upper(Str::random(8))]);
        }

        return view('user.invite', [
            'user' => $user,
            'inviteUrl' => url('/register?invite='.$user->ref_code),
            'downlines' => $user->referrals()->latest()->get(),
            'totalPayback' => Payback::where('user_id', $user->id)->sum('amount'),
            // 每个下线累计带来的返利(按 from_user_id 聚合),供"下线+返利"合并表显示
            'downlineRebates' => Payback::where('user_id', $user->id)
                ->selectRaw('from_user_id, sum(amount) as total')
                ->groupBy('from_user_id')->pluck('total', 'from_user_id'),
        ]);
    }
}
