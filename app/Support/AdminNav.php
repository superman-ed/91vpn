<?php

namespace App\Support;

/**
 * 后台导航结构。
 *
 * `[!!]` 这里只管【看不看得见】，不管【能不能做】。强制在 AdminOnly 中间件上 ——
 * 藏起菜单项不是访问控制，地址栏还在。
 *
 * `[!]` 分组按五大块，且【用户只出现一处】。原提案里"运营中心"下有用户管理、
 * 又单列一个"用户中心"，同一个东西在导航上出现两次，人不知道该点哪个。
 */
class AdminNav
{
    /** 分组 => [[路径, 图标, 标题], ...] 。路径同时是权限判定的键。 */
    public const GROUPS = [
        '用户' => [
            ['/admin/users', 'fas fa-users', '用户管理'],
            ['/admin/online', 'fas fa-signal', '在线用户'],
            ['/admin/tickets', 'far fa-comments', '工单管理'],
        ],
        '交易' => [
            ['/admin/orders', 'fas fa-receipt', '订单管理'],
            ['/admin/finance', 'fas fa-money-bill-wave', '资金流水'],
            ['/admin/rebates', 'fas fa-hand-holding-usd', '返佣记录'],
        ],
        '产品与营销' => [
            ['/admin/plans', 'fas fa-box', '套餐管理'],
            ['/admin/coupons', 'fas fa-ticket-alt', '优惠券'],
            ['/admin/promo', 'fas fa-bullhorn', '推广代理'],
        ],
        '内容' => [
            ['/admin/announcements', 'fas fa-thumbtack', '公告管理'],
            ['/admin/help', 'fas fa-book', '帮助中心'],
            ['/admin/notifications', 'fas fa-paper-plane', '站内信'],
        ],
        '节点与网络' => [
            ['/admin/nodes', 'fas fa-server', '节点管理'],
            ['/admin/rules', 'fas fa-random', '转发规则'],
            ['/admin/relay/monitor', 'fas fa-heartbeat', '中转监控'],
            ['/admin/relay/online-ip', 'fas fa-network-wired', '中转在线IP'],
        ],
        '系统' => [
            ['/admin/admins', 'fas fa-user-shield', '管理员'],
            ['/admin/settings', 'fas fa-cog', '站点设置'],
            ['/admin/system/audit', 'fas fa-clipboard-list', '操作日志'],
            ['/admin/system/login-logs', 'fas fa-sign-in-alt', '登录日志'],
            ['/admin/system/acquisition', 'fas fa-route', '来路统计'],
            ['/admin/system/devices', 'fas fa-mobile-alt', '设备统计'],
            ['/admin/system/crashes', 'fas fa-bug', '崩溃日志'],
            ['/admin/docs', 'fas fa-file-alt', '技术文档'],
        ],
    ];

    /**
     * 这个角色看得见的导航。整组都看不见时，连组标题也不显示 ——
     * 留一个空的分组标题，会让人以为"这里应该有东西，是不是坏了"。
     *
     * @return array<string,array<int,array{0:string,1:string,2:string}>>
     */
    public static function forRole(?string $role): array
    {
        $out = [];
        foreach (self::GROUPS as $group => $items) {
            $visible = array_values(array_filter($items,
                fn (array $i) => AdminAccess::navVisible($role, ltrim($i[0], '/'))));
            if ($visible !== []) {
                $out[$group] = $visible;
            }
        }

        return $out;
    }
}
