<?php

use App\Providers\AppServiceProvider;

/**
 * 收口审计 · 守卫要挡在【路上】，不能只写在文档里。
 *
 * `[!!]` 起因：2026-09-13 我为了验一条检查，用 `php artisan tinker --execute`
 * 往【生产】nodes 表加了一列。tools/repro 的守卫拦得住 tools/repro，
 * 拦不住直接调 artisan —— 而判据写下来不等于会被遵守。
 *
 * `[!]` 这一组只能验【名单本身】：真正的拦截发生在命令启动事件里，
 * 而测试环境恒被放行（否则整套测试都跑不起来）。名单之外的行为
 * 在真实环境里手工验过，记在提交说明里。
 */
it('危险命令在名单里', function () {
    $guarded = (new ReflectionClass(AppServiceProvider::class))
        ->getConstant('GUARDED_COMMANDS');

    // 会写库、且最常见用法是"临时验一件事"的
    expect($guarded)->toContain('tinker')->toContain('db:seed');
    // 会删数据的
    expect($guarded)->toContain('migrate:fresh')->toContain('migrate:reset')
        ->toContain('db:wipe');
});

it('`[!!]` migrate 不在名单里 —— 拦住它等于堵死上线', function () {
    // 一个挡住正常操作的守卫，三天之内就会被人加 --force 绕过去，
    // 那时它连危险的那几条也一起不挡了。
    $guarded = (new ReflectionClass(AppServiceProvider::class))
        ->getConstant('GUARDED_COMMANDS');

    expect($guarded)->not->toContain('migrate');
});

it('定时任务都不在名单里 —— 它们本来就该在生产库上跑', function () {
    $guarded = (new ReflectionClass(AppServiceProvider::class))
        ->getConstant('GUARDED_COMMANDS');

    $scheduled = ['health:sample', 'nodes:mark-offline', 'alive-ips:prune', 'logs:prune',
        'orders:activate-due', 'orders:expire-pending', 'payment:reconcile',
        'stats:snapshot', 'traffic:reset-daily', 'traffic:reset-monthly', 'notify:expiry'];

    expect(array_intersect($guarded, $scheduled))->toBe([]);
});
