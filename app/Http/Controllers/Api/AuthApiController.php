<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// 账户体系:账户名 username + 密码(不使用邮箱)。忘记密码走在线客服人工重置。
class AuthApiController extends Controller
{
    /** POST /api/auth/login —— 账户名+密码登录,返回长效 api_token + 用户信息 */
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_id' => ['nullable', 'string', 'max:128'],
        ]);

        $user = User::where('username', $data['username'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['ret' => 0, 'msg' => '账户名或密码错误'], 401);
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
                'token' => $this->tokenFor($user, $data['device_id'] ?? null),
                'user' => UserApiController::payload($user),
            ],
        ]);
    }

    /** POST /api/auth/register —— 账户名+密码注册,成功即自动登录(返回 token + 用户信息) */
    public function register(Request $request, RegistrationService $registration)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9_]+$/'],
            'name' => ['nullable', 'string', 'max:32'],
            'invite_code' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8'],
            'device_id' => ['nullable', 'string', 'max:128'],
        ], [], ['username' => '账户名']);

        if (User::where('username', $data['username'])->exists()) {
            return response()->json(['ret' => 0, 'msg' => '该账户名已被注册,请更换'], 409);
        }

        try {
            $user = $registration->register([
                'username' => $data['username'],
                'name' => $data['name'] ?? $data['username'],
                'password' => $data['password'],
                'invite_code' => $data['invite_code'] ?? null,
            ], ['ip' => $request->ip()]);
        } catch (ValidationException $e) {
            return response()->json(['ret' => 0, 'msg' => $e->validator->errors()->first()], 422);
        }

        return response()->json([
            'ret' => 1,
            'data' => [
                'token' => $this->tokenFor($user, $data['device_id'] ?? null),
                'user' => UserApiController::payload($user),
            ],
        ]);
    }

    /** 带 device_id → 发/取该设备的 token(下线可单独吊销);否则回退账号级 token(旧端/网页兼容)。 */
    private function tokenFor(User $user, ?string $deviceId): string
    {
        $deviceId = trim((string) $deviceId);

        return $deviceId !== '' ? DeviceToken::issue($user, $deviceId) : $user->api_token;
    }
}
