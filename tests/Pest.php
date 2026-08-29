<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case 绑定
|--------------------------------------------------------------------------
| Feature 测试启动完整 Laravel 应用并用 RefreshDatabase 隔离数据库；
| Unit 测试也启动应用（本项目 Service 层依赖容器/模型）。
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => \Illuminate\Support\Facades\Cache::flush())   // 隔离 Setting 缓存，防跨测试串味
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| 共享测试辅助
|--------------------------------------------------------------------------
*/

/** 造一个带固定 Bearer token 的客户端 API 用户(供各 ClientApi 测试共用) */
function apiUser(array $attr = []): \App\Models\User
{
    return \App\Models\User::factory()->create(array_merge([
        'email' => 'c@test.local', 'password' => 'secret1234', 'api_token' => 'TESTTOKEN123',
        'invite_token' => 'SUBTOKEN32', 'class' => 1, 'class_expire' => now()->addMonth(),
        'transfer_enable' => 10 * 1024 ** 3, 'u' => 1 * 1024 ** 3, 'd' => 2 * 1024 ** 3,
        'money' => 12.5, 'banned' => false,
    ], $attr));
}
