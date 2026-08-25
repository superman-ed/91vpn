<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private CaptchaService $captcha) {}

    /** GET /login */
    public function create(Request $request)
    {
        $c = $this->captcha->make();
        $request->session()->put('captcha_answer', $c['answer']);

        return view('auth.login', ['captchaQuestion' => $c['question']]);
    }

    /** POST /login */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'string'],
        ]);

        if (! $this->captcha->verify($data['captcha'], $request->session()->pull('captcha_answer'))) {
            throw ValidationException::withMessages(['captcha' => '算术验证码错误']);
        }

        $remember = $request->boolean('remember');
        $userId = \App\Models\User::where('email', $data['email'])->value('id');

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $remember)) {
            $this->log($request, 'failed', $data['email'], $userId, '邮箱或密码错误');
            throw ValidationException::withMessages(['email' => '邮箱或密码错误']);
        }

        if (Auth::user()->banned) {
            Auth::logout();
            $this->log($request, 'failed', $data['email'], $userId, '账号已被封禁');
            throw ValidationException::withMessages(['email' => '账号已被封禁']);
        }

        $request->session()->regenerate();
        $this->log($request, 'success', $data['email'], Auth::id());

        return redirect()->intended('/user');
    }

    /** 记一条登录日志（成功/失败通用） */
    private function log(Request $request, string $status, string $email, ?int $userId, string $reason = ''): void
    {
        \App\Models\LoginLog::create([
            'user_id' => $userId,
            'status' => $status,
            'email' => $email,
            'ip' => $request->ip(),
            'location' => \App\Support\GeoIp::locate($request->ip()),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'reason' => $reason,
            'logged_at' => now(),
        ]);
    }

    /** POST /logout */
    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
