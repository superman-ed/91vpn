<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 后台只允许从【管理专用主机名】访问。
 *
 * [decided] 方案 A：管理后台与用户面分成两个主机名，共用一条 Cloudflare
 * 隧道、同一个应用：
 *   admin.<域名>  → 整站套 Cloudflare Access（只有管理员邮箱能进）
 *   app.<域名>    → 不套 Access，给用户网页、客户端 API 和节点心跳用
 *
 * [!!] 两个主机名指向【同一个应用】,所以 app.<域名>/admin/* 本来是可达的
 * —— 那条路径上没有 Access,等于后台从侧门敞着。Cloudflare 那边可以用
 * WAF 规则挡,但那是一条改错就静默失效的外部配置(本轮已经踩过一次:
 * 追加到 INPUT 链尾的 iptables DROP 形同虚设,而日志照样说配好了)。
 * 所以在应用里再挡一道:主机名不对就当这些路由不存在。
 *
 * [!] 返回 404 而不是 403：403 等于告诉探测者"这里确实有后台,只是你
 * 走错了门"。404 什么都不说。
 *
 * [!] ADMIN_HOST 没配时【放行】：本地开发、CI、以及还没切到双主机名的
 * 现网都不该因为少一个环境变量就打不开后台。它是加固项,不是开关。
 *
 * [!!] 挂在 web 组【最前面】,而不是 admin 路由组里 —— Laravel 有中间件
 * 优先级表,Authenticate 在表里,路由组中的书写顺序说了不算:写成
 * ['admin.host','auth','admin'] 时实测仍是 auth 先跑,于是走错主机名的
 * 请求先被跳到登录页(302),而 302 等于告诉探测者"这里有东西"。
 * 挂在组最前面,顺序才是确定的;路径判断由本中间件自己做。
 */
class AdminHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('app.admin_host');

        if ($expected
            && $request->is('admin', 'admin/*')
            && strcasecmp($request->getHost(), $expected) !== 0) {
            abort(404);
        }

        return $next($request);
    }
}
