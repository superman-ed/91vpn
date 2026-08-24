<?php

use App\Models\User;

it('resets used traffic only for members whose anniversary is due', function () {
    // 到期需刷新：开通周年已到（next_reset_at <= now）
    $due = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(10),
        'next_reset_at' => now()->subMinute(),
        // 额度被加油包抬到 110，重置后应归位到基础 100
        'transfer_enable' => 110 * 1024 ** 3, 'base_transfer_enable' => 100 * 1024 ** 3,
        'u' => 60 * 1024 ** 3, 'd' => 20 * 1024 ** 3,
    ]);
    // 未到刷新日：本周期内，不动
    $notDue = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(40),
        'next_reset_at' => now()->addDays(12),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 40 * 1024 ** 3, 'd' => 0,
    ]);
    // 过期会员：不动
    $expired = User::factory()->create([
        'class' => 1, 'class_expire' => now()->subDay(),
        'next_reset_at' => now()->subDay(),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 30 * 1024 ** 3, 'd' => 0,
    ]);
    // 免费用户：不动
    $free = User::factory()->create([
        'class' => 0, 'class_expire' => null, 'next_reset_at' => null,
        'transfer_enable' => 0, 'u' => 5 * 1024 ** 3, 'd' => 0,
    ]);

    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    // 到期会员：已用清零、额度归位到基础配额(抹掉加油包)、刷新日推进到未来
    expect((int) $due->fresh()->u)->toBe(0);
    expect((int) $due->fresh()->d)->toBe(0);
    expect($due->fresh()->transfer_enable)->toBe(100 * 1024 ** 3);   // 110 → 归位 100
    expect($due->fresh()->next_reset_at->isFuture())->toBeTrue();

    // 未到期会员：已用保持不变
    expect((int) $notDue->fresh()->u)->toBe(40 * 1024 ** 3);
    // 过期/免费用户：不动
    expect((int) $expired->fresh()->u)->toBe(30 * 1024 ** 3);
    expect((int) $free->fresh()->u)->toBe(5 * 1024 ** 3);
});

it('advances a badly-overdue anniversary to a future date', function () {
    // 停跑多月导致落后多个周期，应一次推进到未来
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addYear(),
        'next_reset_at' => now()->subMonths(3)->subDays(2),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 50 * 1024 ** 3, 'd' => 0,
    ]);

    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    expect((int) $user->fresh()->u)->toBe(0);
    expect($user->fresh()->next_reset_at->isFuture())->toBeTrue();
});
