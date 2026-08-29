<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\EmailCodeService;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(
        private CaptchaService $captcha,
        private EmailCodeService $emailCode,
        private RegistrationService $registration,
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
        // 注意:此处不加 unique 规则。唯一性检查放到"邮箱验证码校验通过之后",
        // 否则匿名请求可从"已被注册"错误直接枚举出某邮箱是否已注册。
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
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

        // 唯一性检查只在验证码通过后进行:能收到验证码=掌握该邮箱,此时提示"已注册"不构成枚举泄露
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => '该邮箱已注册，请直接登录或找回密码']);
        }

        // 建号（邀请归因 + 受邀奖励）统一走 RegistrationService，与客户端 API 共用
        $user = $this->registration->register($data, [
            'ip' => $request->ip(),
            'referer' => $request->headers->get('referer'),
            'utm' => [
                'source' => $request->session()->get('utm.source'),
                'medium' => $request->session()->get('utm.medium'),
                'campaign' => $request->session()->get('utm.campaign'),
            ],
            'promo' => $request->session()->get('promo'),
        ]);
        $request->session()->forget(['utm', 'promo']);

        Auth::login($user);

        return redirect('/user');
    }
}
