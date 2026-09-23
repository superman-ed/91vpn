<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * 客户端 API 鉴权：Authorization: Bearer {user.api_token}。
 * 长效 token、不绑 IP(与真站 App 一致,避免网页版换 IP 掉线的体验问题)。
 * 认证通过后把用户注入请求,控制器用 $request->user() 取用。
 */
class ClientToken
{
    public function handle(Request $request, Closure $next)
    {
        // 先按设备 token 认(Level A:下线删该 token → 这里查不到 → 401 → 重登);
        // 查不到再回退账号级 users.api_token(旧端/网页/未带 device_id 登录的兼容)。
        $token = $request->bearerToken();
        $user = null;
        if ($token) {
            $user = optional(DeviceToken::where('token', $token)->first())->user
                ?? User::where('api_token', $token)->first();
        }

        if (! $user) {
            return response()->json(['ret' => 0, 'msg' => '未登录或登录已失效'], 401);
        }
        if ($user->banned) {
            return response()->json(['ret' => 0, 'msg' => '账号已被封禁'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
