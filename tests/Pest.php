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
    ->in('Feature', 'Unit');
