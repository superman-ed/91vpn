<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SubController extends Controller
{
    public function __construct(private SubscriptionService $subscription) {}

    /** GET /sub/{token} —— 公开订阅下发，按 ?flag= 或客户端 UA 选格式 */
    public function show(string $token, Request $request)
    {
        $user = User::where('invite_token', $token)->first();
        abort_if($user === null, 404);

        $flag = $request->query('flag') ?: $this->detectFlag($request->userAgent());

        try {
            $body = $this->subscription->generate($user, $flag);
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        $isClash = ! in_array($flag, ['v2ray', 'v2rayn', 'sub', 'base64'], true);

        $this->recordFetch($user, $request, $flag);

        return response($body, Response::HTTP_OK, [
            'Content-Type' => ($isClash ? 'application/yaml' : 'text/plain').'; charset=utf-8',
            'Profile-Update-Interval' => '24',
            'Subscription-Userinfo' => $this->userInfoHeader($user),
        ]);
    }

    /** 按客户端 UA 猜测格式（用户不带 flag 时）*/
    private function detectFlag(?string $ua): string
    {
        $ua = strtolower((string) $ua);
        return match (true) {
            str_contains($ua, 'clash') => 'clash',
            str_contains($ua, 'sing-box') => 'clash',
            str_contains($ua, 'v2ray'), str_contains($ua, 'v2rayn') => 'v2ray',
            str_contains($ua, 'shadowrocket'), str_contains($ua, 'quantumult') => 'sub',
            default => 'clash',
        };
    }

    private function recordFetch(User $user, Request $request, string $flag): void
    {
        \App\Models\SubscribeLog::create([
            'user_id' => $user->id,
            'type' => $flag,
            'ip' => $request->ip(),
            'location' => \App\Support\GeoIp::locate($request->ip()),
            'client' => substr((string) $request->userAgent(), 0, 255),
            'fetched_at' => now(),
        ]);
    }

    private function userInfoHeader(User $user): string
    {
        $expire = $user->class_expire ? $user->class_expire->timestamp : 0;

        return sprintf(
            'upload=%d; download=%d; total=%d; expire=%d',
            $user->u, $user->d, $user->transfer_enable, $expire
        );
    }
}
