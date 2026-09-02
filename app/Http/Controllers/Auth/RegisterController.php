<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(
        private CaptchaService $captcha,
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
        $data = $request->validate([
            'username' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9_]+$/'],
            'name' => ['nullable', 'string', 'max:32'],
            'invite_code' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'captcha' => ['required', 'string'],
        ], [], ['username' => '账户名']);

        // 算术验证码
        if (! $this->captcha->verify($data['captcha'], $request->session()->pull('captcha_answer'))) {
            throw ValidationException::withMessages(['captcha' => '算术验证码错误']);
        }

        if (User::where('username', $data['username'])->exists()) {
            throw ValidationException::withMessages(['username' => '该账户名已被注册，请更换']);
        }

        $data['name'] = $data['name'] ?? $data['username'];

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
