<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * 后台准入 + 按角色的能力判定。
 *
 * `[!!]` 强制发生在【这里】，不是导航上。藏起菜单项不是访问控制 ——
 * 地址栏还在，而知道地址的人正是最可能去试的人。
 *
 * 两道门分工明确：
 *     is_admin     能不能进后台
 *     admin_role   进来之后这一条路由允不允许
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        abort_unless($u && $u->is_admin, 403);

        // `[!!]` capFor 对没声明权限的路由会【抛异常】，不是放行。
        // 默认放行意味着新加一条管理路由就自动对所有角色开放且毫无提示 ——
        // 权限系统最常见的破口正是"忘了给新路由配权限"。
        $cap = AdminAccess::capFor($request->path(), $request->method());

        // false = 这个前缀下不允许该方法（例如对只读的系统日志页发 POST）
        abort_if($cap === false, 403, '这个页面不支持该操作');

        abort_unless(AdminAccess::can($u->admin_role, $cap), 403,
            '你的角色（'.(AdminAccess::ROLES[$u->admin_role] ?? '未设置').'）没有「'
            .(AdminAccess::CAPS[$cap] ?? $cap).'」这项权限');

        return $next($request);
    }
}
