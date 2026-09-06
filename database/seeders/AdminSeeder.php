<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // 账户名是登录/身份键,故按 username 幂等匹配(避免库里已有 admin 却按 email 匹配不到而撞唯一约束)
        User::updateOrCreate(
            ['username' => 'admin'], // App 登录:账户名 admin / 密码 password
            [
                'name' => 'admin',
                'email' => 'admin@test.local',
                'password' => Hash::make('password'),
                'uuid' => (string) Str::uuid(),
                'passwd' => Str::random(6),
                'transfer_enable' => 300 * 1024 ** 3,
                'class' => 2,
                'class_expire' => now()->addDays(30),
                'node_speed_limit' => 200,
                'node_ip_limit' => 7,
                'money' => 8.80,
                'invite_token' => Str::random(32),
                'api_token' => Str::random(60),
                'is_admin' => true,
            ]
        );
    }
}
