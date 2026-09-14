<?php

namespace App\Support;

/**
 * 管理后台的角色与权限。
 *
 * `[!!]` 这个文件是【唯一的权限真相】。三件事都在这里，不许分散：
 *     能力表   有哪些能力
 *     矩阵     哪个角色有哪些能力
 *     路由表   哪条路由需要哪个能力
 * 分散的话，"这个人能不能做这件事"就要翻三个地方才答得出来，
 * 而答不清楚的权限等于没有权限。
 *
 * `[!!]` 权限【写在代码里，不放数据库】。放库里意味着可以在界面上改，
 * 而能在界面上改权限的人，就能给自己加权限 —— 那么角色划分只剩装饰。
 * 写在代码里，每一次改动都留在 git 记录里，且要走一次部署。
 *
 * `[!!]` 真正的强制发生在【路由】上（AdminOnly 中间件），不是导航上。
 * 藏起菜单项不是访问控制 —— 地址栏还在。导航过滤只是体验。
 */
class AdminAccess
{
    /** 角色 → 显示名。`super` 恒等于全部能力。 */
    public const ROLES = [
        'super' => '超级管理员',
        'ops' => '运营',
        'support' => '客服',
        'finance' => '财务',
        'infra' => '运维',
        'auditor' => '只读审计',
    ];

    /** 能力 → 说明。命名一律「对象.动作」，read/write 分开。 */
    public const CAPS = [
        'dashboard.view' => '看总览',
        'users.view' => '看用户',
        'users.edit' => '改用户（封禁/赠送/重置）',
        'orders.view' => '看订单',
        'orders.edit' => '改订单（取消/标记已付）',
        'finance.view' => '看财务与导出',
        'plans.manage' => '管套餐',
        'marketing.manage' => '管优惠券/渠道/返佣',
        'content.manage' => '管公告/帮助/站内信',
        'tickets.manage' => '处理工单',
        'nodes.view' => '看节点与健康',
        'nodes.manage' => '改节点/部署',
        'rules.manage' => '改中转规则（配置发布）',
        'system.view' => '看系统日志与运行状况',
        'system.manage' => '改系统设置',
        'admins.manage' => '管管理员与角色',
    ];

    /**
     * 角色 → 能力。
     *
     * `[!]` 依据是"这个岗位为了干活【必须】看到什么"，不是"给了也无妨"。
     * 每多给一项，出事时的排查面就大一圈；而多数越权不是恶意，是手滑。
     */
    public const MATRIX = [
        'ops' => [
            'dashboard.view', 'users.view', 'users.edit', 'orders.view', 'orders.edit',
            'finance.view', 'plans.manage', 'marketing.manage', 'content.manage',
            'tickets.manage',
            // `[!]` 运营给 nodes.view 而不给 manage：卖套餐要知道哪个区域能不能用，
            // 但不该能改节点。这正是"运营看不到 Relay 细节"那条的落点。
            'nodes.view',
        ],
        'support' => [
            'dashboard.view', 'users.view', 'users.edit', 'orders.view',
            'tickets.manage', 'content.manage', 'nodes.view',
        ],
        'finance' => [
            'dashboard.view', 'users.view', 'orders.view', 'orders.edit', 'finance.view',
        ],
        'infra' => [
            'dashboard.view', 'nodes.view', 'nodes.manage', 'rules.manage',
            'system.view', 'system.manage',
        ],
        'auditor' => [
            'dashboard.view', 'users.view', 'orders.view', 'finance.view',
            'nodes.view', 'system.view',
        ],
    ];

    /**
     * 路由 → 所需能力。按【路径前缀】匹配，长的先匹配。
     *
     * 每项是 [前缀, 读能力, 写能力]；GET/HEAD 用读，其余用写。
     * 写能力为 null 表示这个前缀下不该有写操作（有就会被拒，且测试会报出来）。
     *
     * `[!!]` 有几条 GET 其实是【动作或敏感导出】，单独提前列出 ——
     * 靠 HTTP 方法推断权限，在这几条上会给错。
     */
    public const ROUTES = [
        // —— 先列特例，它们必须排在各自的通用前缀之前 ——
        ['admin/finance/export', 'finance.view', null],
        ['admin/orders/export', 'finance.view', null],
        ['admin/users/export', 'users.view', null],
        ['admin/nodes/{node}/deploy-identity', 'nodes.manage', 'nodes.manage'],
        ['admin/nodes/{node}/deploy', 'nodes.manage', 'nodes.manage'],
        ['admin/nodes/{node}/diagnose', 'nodes.view', null],

        // —— 通用前缀 ——
        ['admin/users', 'users.view', 'users.edit'],
        ['admin/online', 'users.view', null],
        ['admin/orders', 'orders.view', 'orders.edit'],
        ['admin/finance', 'finance.view', null],
        ['admin/rebates', 'marketing.manage', 'marketing.manage'],
        ['admin/promo', 'marketing.manage', 'marketing.manage'],
        ['admin/coupons', 'marketing.manage', 'marketing.manage'],
        ['admin/plans', 'plans.manage', 'plans.manage'],
        ['admin/announcements', 'content.manage', 'content.manage'],
        ['admin/help', 'content.manage', 'content.manage'],
        ['admin/notifications', 'content.manage', 'content.manage'],
        ['admin/downloads', 'content.manage', 'content.manage'],
        ['admin/banners', 'content.manage', 'content.manage'],
        ['admin/tickets', 'tickets.manage', 'tickets.manage'],
        ['admin/nodes', 'nodes.view', 'nodes.manage'],
        ['admin/rules', 'rules.manage', 'rules.manage'],
        ['admin/entry-domains', 'rules.manage', 'rules.manage'],
        ['admin/topology', 'nodes.view', null],
        ['admin/relay', 'nodes.view', null],
        ['admin/admins', 'admins.manage', 'admins.manage'],
        ['admin/settings', 'system.manage', 'system.manage'],
        ['admin/system', 'system.view', null],
        ['admin/docs', 'dashboard.view', null],
        // `[!]` 改自己的密码：每个管理员都该能做，不需要任何额外能力。
        ['admin/account', 'dashboard.view', 'dashboard.view'],
    ];

    /**
     * 精确匹配的路径（不作为前缀参与匹配）。
     *
     * `[!!]` 总览必须放这里，【不能】作为前缀条目。
     * 一条 `['admin', ...]` 的前缀条目会匹配所有 `admin/*` ——
     * 于是"没声明权限就抛错"这个性质被自己的兜底条目消掉了：
     * 新加的管理路由会被静默接住（GET 人人可读、写操作 403），
     * 而覆盖测试照常通过 —— 它验的是"不抛异常"，兜底正好满足。
     * 2026-09-13 加下载/Banner 路由时才发现，当时测试是绿的。
     */
    public const EXACT = [
        'admin' => ['dashboard.view', null],
    ];

    /** 这个角色有没有这项能力。 */
    public static function can(?string $role, string $cap): bool
    {
        if ($role === 'super') {
            return true;
        }
        if ($role === null || ! isset(self::MATRIX[$role])) {
            return false;
        }

        return in_array($cap, self::MATRIX[$role], true);
    }

    /**
     * 这条路径 + 方法需要什么能力。
     *
     * @return string|false  能力名；false 表示【这个前缀下不允许该方法】
     * @throws \RuntimeException 路径没有任何条目 —— 那是配置漏了，不能放行
     */
    public static function capFor(string $uri, string $method): string|false
    {
        $uri = trim($uri, '/');
        $write = ! in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);

        if (isset(self::EXACT[$uri])) {
            [$read, $writeCap] = self::EXACT[$uri];

            return ($write ? $writeCap : $read) ?? false;
        }

        foreach (self::ROUTES as [$prefix, $read, $writeCap]) {
            if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
                $cap = $write ? $writeCap : $read;

                return $cap ?? false;
            }
        }

        // `[!!]` 匹配不到【不是放行,是抛错】。默认放行意味着新加一条路由
        // 就自动对所有角色开放,而且没有任何提示 —— 权限系统最常见的破口
        // 正是"忘了给新路由配权限"。这里抛错,测试会在合并前就抓到。
        throw new \RuntimeException("管理路由 {$uri} 没有声明所需权限（见 AdminAccess::ROUTES）");
    }

    /** 这个角色能看到的导航项（纯体验，强制在中间件）。 */
    public static function navVisible(?string $role, string $path): bool
    {
        try {
            $cap = self::capFor($path, 'GET');
        } catch (\RuntimeException) {
            return false;
        }

        return $cap !== false && self::can($role, $cap);
    }
}
