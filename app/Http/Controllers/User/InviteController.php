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
            'paybacks' => $user->paybacks()->with('fromUser')->latest()->take(20)->get(),
        ]);
    }
}
