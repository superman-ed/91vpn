<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function __construct(private EmailCodeService $emailCode) {}

    /** GET /password/forgot */
    public function forgot()
    {
        return view('auth.forgot');
    }

    /** POST /password/send —— 发送重置验证码（走统一发信服务：真发 + 记录 + 可代查） */
    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // 无论邮箱是否存在都返回成功（不泄露账号是否注册），仅在存在时才真发
        if (User::where('email', $data['email'])->exists()) {
            $this->emailCode->send($data['email']);
        }

        return response()->json(['ok' => true, 'message' => '若邮箱已注册，验证码已发送']);
    }

    /** POST /password/reset —— 用验证码重置密码 */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $this->emailCode->verify($data['email'], $data['code'])) {
            throw ValidationException::withMessages(['code' => '验证码错误或已过期']);
        }

        // 能通过验证码=掌握该邮箱;此处不区分"账号不存在",统一回验证码错误,避免枚举
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            throw ValidationException::withMessages(['code' => '验证码错误或已过期']);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return redirect('/login')->with('status', '密码已重置，请用新密码登录');
    }
}
