<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\EmailCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(
        private CaptchaService $captcha,
        private EmailCodeService $emailCode,
    ) {}

    /** GET /register */
    public function create(Request $request)
    {
        $c = $this->captcha->make();
        $request->session()->put('captcha_answer', $c['answer']);

        return view('auth.register', ['captchaQuestion' => $c['question']]);
    }

    /** POST /register */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'email_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:32'],
            'invite_code' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'captcha' => ['required', 'string'],
        ]);

        // 算术验证码
        if (! $this->captcha->verify($data['captcha'], $request->session()->pull('captcha_answer'))) {
            throw ValidationException::withMessages(['captcha' => '算术验证码错误']);
        }

        // 邮箱验证码
        if (! $this->emailCode->verify($data['email'], $data['email_code'])) {
            throw ValidationException::withMessages(['email_code' => '邮箱验证码错误或已过期']);
        }

        // 邀请码（选填）：支持一次性邀请码 或 用户永久推广码
        $invite = null;
        $refByUserId = null;
        if (! empty($data['invite_code'])) {
            $invite = InviteCode::where('code', $data['invite_code'])->whereNull('used_by')->first();
            if ($invite) {
                $refByUserId = $invite->user_id;
            } else {
                $inviter = User::where('ref_code', $data['invite_code'])->first();
                if (! $inviter) {
                    throw ValidationException::withMessages(['invite_code' => '邀请码无效或已被使用']);
                }
                $refByUserId = $inviter->id;
            }
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'uuid' => (string) Str::uuid(),
            'passwd' => Str::lower(Str::random(6)),
            'transfer_enable' => 0,
            'class' => 0,
            'class_expire' => now(),
            'ref_code' => Str::upper(Str::random(8)),
            'invite_token' => Str::random(32),
            'api_token' => Str::random(60),
            'ref_by' => $refByUserId,
            'reg_ip' => $request->ip(),
            'reg_referer' => Str::limit((string) $request->headers->get('referer'), 490, ''),
        ]);

        if ($invite) {
            $invite->update(['used_by' => $user->id]);
        }

        // 受邀注册奖励：通过邀请注册即得初始资金（后台可配，默认 1 元）
        if ($refByUserId && signup_bonus() > 0) {
            $bonus = signup_bonus();
            $user->increment('money', $bonus);
            \App\Models\BalanceLog::create([
                'user_id' => $user->id,
                'amount' => $bonus,
                'type' => 'bonus',
                'balance_after' => $user->fresh()->money,
                'remark' => '邀请注册奖励',
            ]);
        }

        Auth::login($user);

        return redirect('/user');
    }
}
