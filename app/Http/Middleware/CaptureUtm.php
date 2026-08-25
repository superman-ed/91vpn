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
            // 代理推广码（首次来源优先）
            $ch = $request->query('ch');
            if (is_string($ch) && $ch !== '' && ! $request->session()->has('promo')) {
                $request->session()->put('promo', mb_substr($ch, 0, 64));
            }
        }

        return $next($request);
    }
}
