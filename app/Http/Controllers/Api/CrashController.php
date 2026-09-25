<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrashLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 自研客户端崩溃/未捕获错误上报（自建,不用第三方 Sentry）。
 * 公开路由:游客也可能崩溃,token 可选——带上则归属到用户,否则 user_id 为空。
 */
class CrashController extends Controller
{
    /** POST /api/crash */
    public function report(Request $request)
    {
        // token 可选:有效则归属用户
        $token = $request->bearerToken();
        $user = $token ? User::where('api_token', $token)->first() : null;

        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'string', 'max:16'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:128'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:20000'],
        ]);

        $message = Str::limit(trim($data['message']), 490, '');

        // `[!!]` stack 也要截断,而且必须【按字节】。
        //   crash_logs.stack 是 TEXT(65,535【字节】),而校验写的是 max:20000
        //   ——那是【字符】数。ASCII 堆栈 20000 字符约 20000 字节,没事;
        //   但 4 字节字符(emoji 等)顶满就是 80,000 字节,超出 TEXT 上限,
        //   而 MySQL 开着 STRICT_TRANS_TABLES → 直接抛错 → HTTP 500。
        //   [D] 2026-09-24 实测:20000 个 🙂(80,000 字节)确实 500。
        //
        // `[!]` 崩溃上报是客户端"发了就不管"的遥测:500 意味着那条报告【直接丢失】,
        //   而丢的正是内容最长的那些。所以和上面 message 一样【截断而不是拒绝】——
        //   这个做法作者已经用在 message 上了,stack 只是漏了。
        //   mb_strcut 按字节切且不会把一个字符切成两半。
        $stack = $data['stack'] ?? null;
        if ($stack !== null) {
            $stack = mb_strcut($stack, 0, 60000, 'UTF-8');
        }
        // 归一化摘要(去掉数字/十六进制/引号内容)后哈希,用于把"同一个 bug"聚合在一起
        $norm = preg_replace(['/0x[0-9a-f]+/i', '/\d+/', '/[\'"][^\'"]*[\'"]/'], ['', '', ''], $message);
        $fingerprint = substr(sha1(($data['platform'] ?? '').'|'.trim((string) $norm)), 0, 40);

        CrashLog::create([
            'user_id' => $user?->id,
            'device_id' => $data['device_id'] ?? '',
            'platform' => strtolower($data['platform'] ?? ''),
            'brand' => $data['brand'] ?? '',
            'model' => $data['model'] ?? '',
            'os_version' => $data['os_version'] ?? '',
            'app_version' => $data['app_version'] ?? '',
            'message' => $message,
            'stack' => $stack,
            'fingerprint' => $fingerprint,
            'ip' => $request->ip() ?? '',
        ]);

        return response()->json(['ret' => 1]);
    }
}
