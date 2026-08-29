<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
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
}
