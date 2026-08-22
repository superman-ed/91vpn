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

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $remember)) {
            throw ValidationException::withMessages(['email' => '邮箱或密码错误']);
        }

        if (Auth::user()->banned) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => '账号已被封禁']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/user');
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
