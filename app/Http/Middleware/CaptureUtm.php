<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 捕获推广链接的 UTM 参数(首次来源优先),存 session,注册时落库。
 * 用户从 `?utm_source=telegram&utm_campaign=spring` 进入任意页面即被记录。
 */
class CaptureUtm
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('get')) {
            if (! $request->session()->has('utm')) {
                $utm = array_filter([
                    'source' => $request->query('utm_source'),
                    'medium' => $request->query('utm_medium'),
                    'campaign' => $request->query('utm_campaign'),
                ], fn ($v) => is_string($v) && $v !== '');

                if (! empty($utm)) {
                    $request->session()->put('utm', array_map(fn ($v) => mb_substr($v, 0, 120), $utm));
                }
            }
            // 代理推广码（首次来源优先）+ 访问量统计
            $ch = $request->query('ch');
            if (is_string($ch) && $ch !== '') {
                $code = mb_substr($ch, 0, 64);
                if (! $request->session()->has('promo')) {
                    $request->session()->put('promo', $code);
                }
                $this->countVisit($request, $code);
            }
        }

        return $next($request);
    }

    /** 记推广码访问：PV 每次+1；UV 同 session 同码只算一次 */
    private function countVisit(Request $request, string $code): void
    {
        $channel = \App\Models\PromoChannel::where('code', $code)->where('enabled', true)->first();
        if (! $channel) {
            return;
        }
        $seenKey = 'promo_seen:'.$code;
        $isNew = ! $request->session()->has($seenKey);
        if ($isNew) {
            $request->session()->put($seenKey, 1);
        }
        $channel->increment('pv');
        if ($isNew) {
            $channel->increment('uv');
        }
    }
}
