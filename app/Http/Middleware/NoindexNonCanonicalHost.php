<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 只让官网品牌域(APP_URL 的 host)被搜索引擎收录。
 *
 * 三个域同一个后端(91vpn.com 官网 / app.91app.shop API / sub.91app.shop 订阅),
 * 若 app./sub. 被爬到会成官网的重复内容、分散权重。这里对"非 APP_URL host"的响应
 * 下发 `X-Robots-Tag: noindex, nofollow`,搜索引擎只索引官网域。
 *
 * 说明:随 APP_URL 走 —— 现在 APP_URL=app.91app.shop 时它是可索引域;切成 91vpn.com 后
 * 自动变成"只索引 91vpn.com、其余 noindex",无需再改这里。API/订阅是非 HTML,带上也无害。
 */
class NoindexNonCanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($canonical && $request->getHost() !== $canonical) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
