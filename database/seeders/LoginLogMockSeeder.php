<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoginLogMockSeeder extends Seeder
{
    /** 登录日志 mock：成功 + 零散失败 + 一个触发暴破告警的 IP */
    public function run(): void
    {
        $users = User::inRandomOrder()->limit(20)->get(['id', 'email']);
        if ($users->isEmpty()) {
            return;
        }
        $clients = [
            'ClashforWindows/0.20.39', 'v2rayN/6.23', 'Shadowrocket/2.2.35', 'ClashMetaForAndroid/2.9.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1',
        ];
        $ip = fn () => random_int(1, 223).'.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254);
        $ts = fn () => now()->subDays(random_int(0, 6))->subMinutes(random_int(0, 1439));
        $rows = [];

        // 40 条成功登录
        for ($i = 0; $i < 40; $i++) {
            $u = $users->random();
            $t = $ts();
            $rows[] = ['user_id' => $u->id, 'status' => 'success', 'email' => $u->email, 'ip' => $ip(),
                'location' => '', 'user_agent' => $clients[array_rand($clients)], 'reason' => '',
                'logged_at' => $t, 'created_at' => $t, 'updated_at' => $t];
        }

        // 8 条零散失败(不同 IP，不触发告警)
        for ($i = 0; $i < 8; $i++) {
            $u = $users->random();
            $t = $ts();
            $rows[] = ['user_id' => $u->id, 'status' => 'failed', 'email' => $u->email, 'ip' => $ip(),
                'location' => '', 'user_agent' => $clients[array_rand($clients)], 'reason' => '邮箱或密码错误',
                'logged_at' => $t, 'created_at' => $t, 'updated_at' => $t];
        }

        // 暴破：同一个 IP 近 24h 内狂试多个账号(触发告警)
        $attackIp = '45.83.192.77';
        foreach ($users->take(9) as $k => $u) {
            $t = now()->subHours(random_int(0, 20))->subMinutes(random_int(0, 59));
            $rows[] = ['user_id' => $u->id, 'status' => 'failed', 'email' => $u->email, 'ip' => $attackIp,
                'location' => '', 'user_agent' => 'python-requests/2.31.0', 'reason' => '邮箱或密码错误',
                'logged_at' => $t, 'created_at' => $t, 'updated_at' => $t];
        }
        // 撞不存在的账号
        foreach (['admin@vpn.com', 'root@vpn.com', 'test@vpn.com'] as $email) {
            $t = now()->subHours(random_int(0, 20));
            $rows[] = ['user_id' => null, 'status' => 'failed', 'email' => $email, 'ip' => $attackIp,
                'location' => '', 'user_agent' => 'python-requests/2.31.0', 'reason' => '邮箱或密码错误',
                'logged_at' => $t, 'created_at' => $t, 'updated_at' => $t];
        }

        // 补真实地点
        foreach ($rows as &$r) {
            $r['location'] = \App\Support\GeoIp::locate($r['ip']);
        }
        unset($r);

        DB::table('login_logs')->insert($rows);
    }
}
