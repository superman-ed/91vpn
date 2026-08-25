<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemMockSeeder extends Seeder
{
    /** 给系统板块页面造 mock：登录日志 / 设备统计 / 操作日志 / 来路统计 */
    public function run(): void
    {
        $userIds = User::pluck('id')->all();
        if (empty($userIds)) {
            return;
        }
        $adminId = User::where('is_admin', true)->value('id') ?? $userIds[0];

        $clients = [
            'ClashforWindows/0.20.39', 'ClashX/1.118.0', 'ClashMetaForAndroid/2.9.0',
            'clash-verge/1.5.0', 'v2rayN/6.23', 'v2rayNG/1.8.5', 'Shadowrocket/2.2.35',
            'sing-box 1.8.0', 'Stash/2.5.0', 'Quantumult%20X 1.4.0', 'Surge/5.8.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36',
        ];
        $cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '香港', '东京', '洛杉矶', '新加坡', '首尔'];
        $ip = fn () => random_int(1, 223).'.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254);
        $ts = fn () => now()->subDays(random_int(0, 13))->subMinutes(random_int(0, 1439));

        // 登录日志
        $login = [];
        for ($i = 0; $i < 60; $i++) {
            $t = $ts();
            $login[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'ip' => $ip(), 'location' => $cities[array_rand($cities)],
                'user_agent' => $clients[array_rand($clients)],
                'logged_at' => $t, 'created_at' => $t, 'updated_at' => $t,
            ];
        }
        DB::table('login_logs')->insert($login);

        // 订阅拉取（设备统计来源）
        $types = ['clash', 'v2ray', 'general'];
        $sub = [];
        for ($i = 0; $i < 80; $i++) {
            $t = $ts();
            $sub[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'type' => $types[array_rand($types)],
                'ip' => $ip(), 'location' => $cities[array_rand($cities)],
                'client' => $clients[array_rand($clients)],
                'fetched_at' => $t, 'created_at' => $t, 'updated_at' => $t,
            ];
        }
        DB::table('subscribe_logs')->insert($sub);

        // 操作日志
        $emails = User::inRandomOrder()->limit(8)->pluck('email')->all();
        $pick = fn () => $emails[array_rand($emails)];
        $actions = [
            ['user.update', fn () => "更新用户 {$pick()}", 'User'],
            ['user.ban', fn () => "封禁用户 {$pick()}", 'User'],
            ['user.grant', fn () => "为 {$pick()} 开通「VIP 月付」", 'User'],
            ['user.reset_password', fn () => "重置 {$pick()} 登录密码", 'User'],
            ['order.mark_paid', fn () => '手动标记订单 O'.now()->format('YmdHis').' 已支付并发货', 'Order'],
            ['order.cancel', fn () => '取消订单 O'.now()->format('YmdHis'), 'Order'],
            ['node.update', fn () => '更新节点「香港 IEPL 01」', 'Node'],
            ['setting.update', fn () => '更新站点设置', null],
            ['email.peek_code', fn () => "代查 {$pick()} 的验证码（命中）", null],
        ];
        $audit = [];
        for ($i = 0; $i < 30; $i++) {
            $a = $actions[array_rand($actions)];
            $t = $ts();
            $audit[] = [
                'admin_id' => $adminId, 'action' => $a[0], 'description' => ($a[1])(),
                'target_type' => $a[2], 'target_id' => $a[2] ? random_int(1, 50) : null,
                'ip' => $ip(), 'created_at' => $t, 'updated_at' => $t,
            ];
        }
        DB::table('audit_logs')->insert($audit);

        // 来路统计：给未被邀请的用户补注册来源
        $referers = [
            'https://t.me/freevpnshare', 'https://www.nodeseek.com/post-8899-1',
            'https://www.google.com/search?q=机场推荐', 'https://twitter.com/vpndeals/status/1',
            'https://www.reddit.com/r/dumbclub', 'https://www.v2ex.com/t/123456',
            null, null, null,   // null = 直接访问
        ];
        foreach (User::whereNull('ref_by')->get() as $u) {
            $u->update([
                'reg_ip' => $ip(),
                'reg_referer' => $referers[array_rand($referers)],
            ]);
        }
    }
}
