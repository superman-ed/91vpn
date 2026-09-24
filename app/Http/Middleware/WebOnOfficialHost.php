<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 网页(给人看的页面)只在官网域(APP_URL host,如 91vpn.com)提供。
 * app.91app.shop 因此只剩客户端 API(/api/* 不在 web 组),成为纯 API 域;
 * 其它非官网域收到网页请求时,302 到官网域同路径。
 *
 * [!!] 绝不重定向的放行路径(误跳会闯大祸):
 *   - /sub/*   订阅:误跳会把每个用户的订阅链接搬到官网域(链接已嵌客户端,收不回)
 *   - /mod_mu/* 节点 WebAPI(POST + node.secret):302 会毁掉节点上报
 *   - /pay/*   支付网关异步回调(POST):302 会丢回调
 *   - /up      健康检查
 * [!] 后台域(ADMIN_HOST,如 summer.91app.shop)整体放行 —— 它有自己的登录/页面,
 *   由 AdminHost 中间件 + CF Access 管,不能被跳回官网。
 */
class WebOnOfficialHost
{
    private const PASSTHROUGH = ['sub', 'sub/*', 'mod_mu', 'mod_mu/*', 'pay', 'pay/*', 'up'];

    public function handle(Request $request, Closure $next)
    {
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);
        $adminHost = (string) config('app.admin_host');
        $host = $request->getHost();

        if ($canonical
            && $host !== $canonical
            && ($adminHost === '' || $host !== $adminHost)
            && ! $request->is(...self::PASSTHROUGH)) {
            $target = rtrim((string) config('app.url'), '/').$request->getRequestUri();

            return redirect()->away($target, 302);
        }

        return $next($request);
    }
}
