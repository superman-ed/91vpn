<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InviteApiController extends Controller
{
    /** GET /api/invite —— 我的推广码 / 邀请链接 / 累计返利 / 下线明细(含各自带来的返利) */
    public function index(Request $request)
    {
        $user = $request->user();

        // 兜底:老用户没有 ref_code 则补一个(与网页版一致)
        if (empty($user->ref_code)) {
            $user->update(['ref_code' => Str::upper(Str::random(8))]);
        }

        $rebates = Payback::where('user_id', $user->id)
            ->selectRaw('from_user_id, sum(amount) as total')
            ->groupBy('from_user_id')->pluck('total', 'from_user_id');

        $downlines = $user->referrals()->latest()->get()->map(fn (User $d) => [
            'id' => $d->id,
            'name' => $this->maskEmail($d->email),
            'registered_at' => $d->created_at?->toDateTimeString(),
            'rebate' => (float) ($rebates[$d->id] ?? 0),
        ])->values();

        return response()->json(['ret' => 1, 'data' => [
            'ref_code' => $user->ref_code,
            'invite_url' => url('/register?invite='.$user->ref_code),
            'total_payback' => (float) Payback::where('user_id', $user->id)->sum('amount'),
            'downline_count' => $downlines->count(),
            'downlines' => $downlines,
        ]]);
    }

    /** 邮箱脱敏:ab***@domain,保护下线隐私 */
    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '用户';
        }
        [$name, $domain] = explode('@', $email, 2);
        $head = Str::substr($name, 0, 2);

        return $head.'***@'.$domain;
    }
}
