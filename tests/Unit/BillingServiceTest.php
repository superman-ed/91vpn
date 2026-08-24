<?php

use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;

it('delivers a plan: adds traffic, sets class, extends expiry', function () {
    $user = User::factory()->create([
        'class' => 0,
        'class_expire' => now()->subDay(),   // 已过期
        'transfer_enable' => 0,
        'node_speed_limit' => 0,
        'node_ip_limit' => 0,
    ]);
    $plan = Plan::create([
        'name' => 'VIP②', 'price' => 50, 'period' => 'month',
        'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200,
        'ip_limit' => 7, 'duration_days' => 30,
    ]);

    app(BillingService::class)->deliver($user, $plan);

    $user->refresh();
    expect($user->transfer_enable)->toBe(300 * 1024 ** 3);
    expect($user->base_transfer_enable)->toBe(300 * 1024 ** 3);   // 记录重置基准
    expect($user->class)->toBe(2);
    expect($user->node_speed_limit)->toBe(200);
    expect($user->node_ip_limit)->toBe(7);
    // 过期用户从 now 起算 30 天
    expect($user->class_expire->toDateString())->toBe(now()->addDays(30)->toDateString());
    // 下次流量刷新 = 开通日 + 1 个月（月周年，非日历1号）
    expect($user->next_reset_at->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
});

it('stacks expiry from future date when renewing before expiry', function () {
    $future = now()->addDays(10);
    $user = User::factory()->create(['class' => 1, 'class_expire' => $future, 'transfer_enable' => 0]);
    $plan = Plan::create([
        'name' => 'VIP①', 'price' => 30, 'period' => 'month',
        'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30,
    ]);

    app(BillingService::class)->deliver($user, $plan);

    // 未过期续费：从原到期日叠加 → 10 + 30 = 40 天后
    expect($user->fresh()->class_expire->toDateString())->toBe($future->copy()->addDays(30)->toDateString());
});

it('sets fixed monthly quota and resets usage on renewal', function () {
    // 续费不累加流量：设为月配额并清零已用（流量固定只延长有效期）
    $user = User::factory()->create([
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 80 * 1024 ** 3, 'd' => 10 * 1024 ** 3,
        'class' => 1, 'class_expire' => now()->addDays(5),
    ]);
    $plan = Plan::create([
        'name' => 'VIP①', 'price' => 30, 'period' => 'month',
        'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30,
    ]);

    app(BillingService::class)->deliver($user, $plan);

    $user->refresh();
    expect($user->transfer_enable)->toBe(100 * 1024 ** 3);   // 固定月配额，不累加
    expect((int) $user->u)->toBe(0);                          // 已用清零
    expect((int) $user->d)->toBe(0);
});
