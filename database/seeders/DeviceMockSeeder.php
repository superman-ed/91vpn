<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceMockSeeder extends Seeder
{
    /** 安装设备 mock：真实机型/系统版本/App版本，覆盖各平台，含在线/离线 */
    public function run(): void
    {
        $userIds = User::pluck('id')->all();
        if (empty($userIds)) {
            return;
        }

        // [platform, brand, model, os_version 候选]
        $catalog = [
            ['android', 'Xiaomi', 'Redmi K70 Pro', ['14', '13']],
            ['android', 'Xiaomi', 'Xiaomi 14', ['14']],
            ['android', 'HUAWEI', 'Mate 60 Pro', ['12', '13']],
            ['android', 'samsung', 'Galaxy S24 Ultra', ['14']],
            ['android', 'samsung', 'Galaxy S23', ['13', '14']],
            ['android', 'OnePlus', 'PJZ110', ['14']],
            ['android', 'vivo', 'V2324A', ['14']],
            ['android', 'OPPO', 'PHZ110', ['13']],
            ['ios', 'Apple', 'iPhone 15 Pro Max', ['17.4', '17.3', '17.2']],
            ['ios', 'Apple', 'iPhone 15', ['17.4', '17.2']],
            ['ios', 'Apple', 'iPhone 14 Pro', ['17.3', '16.6']],
            ['ios', 'Apple', 'iPhone 13', ['16.7', '17.2']],
            ['ios', 'Apple', 'iPad Pro 11', ['17.4']],
            ['windows', 'Microsoft', 'Windows 11', ['11']],
            ['windows', 'Microsoft', 'Windows 10', ['10']],
            ['macos', 'Apple', 'MacBook Pro', ['14.4', '13.6']],
            ['macos', 'Apple', 'MacBook Air M2', ['14.4']],
        ];
        $appVersions = ['1.3.0', '1.3.0', '1.3.0', '1.2.1', '1.2.0', '1.1.0'];   // 加权:多数已升到最新
        $ip = fn () => random_int(1, 223).'.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254);

        $rows = [];
        $used = [];   // user_id => device 序号，避免同用户 device_id 撞
        for ($i = 0; $i < 70; $i++) {
            $uid = $userIds[array_rand($userIds)];
            $c = $catalog[array_rand($catalog)];
            $seq = ($used[$uid] = ($used[$uid] ?? 0) + 1);
            // 70% 近在线、30% 几天前
            $seen = random_int(1, 100) <= 70
                ? now()->subMinutes(random_int(0, 12))
                : now()->subDays(random_int(1, 20))->subHours(random_int(0, 23));
            $rows[] = [
                'user_id' => $uid,
                'device_id' => 'dev-'.$uid.'-'.$seq.'-'.substr(md5($uid.$i), 0, 8),
                'platform' => $c[0], 'brand' => $c[1], 'model' => $c[2],
                'os_version' => $c[3][array_rand($c[3])],
                'app_version' => $appVersions[array_rand($appVersions)],
                'ip' => $ip(), 'last_seen' => $seen,
                'created_at' => $seen, 'updated_at' => $seen,
            ];
        }

        DB::table('devices')->insert($rows);
    }
}
