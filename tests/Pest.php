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
