<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailCodeService;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(private EmailCodeService $emailCode) {}

    /** POST /api/auth/login —— 邮箱+密码登录,返回长效 api_token + 用户信息 */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['ret' => 0, 'msg' => '邮箱或密码错误'], 401);
        }
        if ($user->banned) {
            return response()->json(['ret' => 0, 'msg' => '账号已被封禁'], 403);
        }

        // 老用户可能没有 api_token,补发一个(长效,不轮换)
        if (empty($user->api_token)) {
            $user->update(['api_token' => Str::random(60)]);
        }

        return response()->json([
            'ret' => 1,
            'data' => [
                'token' => $user->api_token,
                'user' => UserApiController::payload($user),
            ],
        ]);
    }

    /**
     * POST /api/auth/send-code —— 注册用邮箱验证码(发往任意邮箱,唯一性检查留到注册时)。
     * 原生 App 无 session,注册防滥用以「掌握邮箱 + 一次性验证码 + 发码限流」为闸门,故不含算术码。
     */
    public function sendCode(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            $this->emailCode->send($data['email']);
        } catch (\Throwable $e) {
            Log::warning('客户端注册验证码发送失败', ['email' => $data['email'], 'err' => $e->getMessage()]);

            return response()->json(['ret' => 0, 'msg' => '邮件发送失败，请稍后重试或联系客服'], 500);
        }

        return response()->json([
            'ret' => 1,
            'msg' => smtp_configured() ? '验证码已发送至邮箱，请查收（含垃圾箱）' : '验证码已发送（开发环境请查看服务器日志）',
        ]);
    }

    /** POST /api/auth/register —— 邮箱验证码注册,成功即自动登录(返回 token + 用户信息) */
    public function register(Request $request, RegistrationService $registration)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'email_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:32'],
            'invite_code' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // 邮箱验证码(注册闸门)
        if (! $this->emailCode->verify($data['email'], $data['email_code'])) {
            return response()->json(['ret' => 0, 'msg' => '邮箱验证码错误或已过期'], 422);
        }

        // 唯一性检查只在验证码通过后进行:能收到验证码=掌握该邮箱,此时提示已注册不构成枚举泄露
        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['ret' => 0, 'msg' => '该邮箱已注册，请直接登录或找回密码'], 409);
        }

        try {
            $user = $registration->register($data, ['ip' => $request->ip()]);
        } catch (ValidationException $e) {
            return response()->json(['ret' => 0, 'msg' => $e->validator->errors()->first()], 422);
        }

        return response()->json([
            'ret' => 1,
            'data' => [
                'token' => $user->api_token,
                'user' => UserApiController::payload($user),
            ],
        ]);
    }

    /** POST /api/auth/forgot —— 发送找回密码验证码(存在才真发,响应恒为成功,不泄露账号是否注册) */
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        if (User::where('email', $data['email'])->exists()) {
            $this->emailCode->send($data['email']);
        }

        return response()->json(['ret' => 1, 'msg' => '若邮箱已注册，验证码已发送']);
    }

    /** POST /api/auth/reset —— 用验证码重置密码 */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // 能通过验证码=掌握该邮箱;账号不存在也统一回「验证码错误」,避免枚举
        if (! $this->emailCode->verify($data['email'], $data['code'])) {
            return response()->json(['ret' => 0, 'msg' => '验证码错误或已过期'], 422);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['ret' => 0, 'msg' => '验证码错误或已过期'], 422);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['ret' => 1, 'msg' => '密码已重置，请用新密码登录']);
    }
}
