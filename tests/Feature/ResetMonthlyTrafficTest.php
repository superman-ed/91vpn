<?php

use App\Models\User;

it('resets used traffic for active members only', function () {
    $active = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(10),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 60 * 1024 ** 3, 'd' => 20 * 1024 ** 3,
    ]);
    $expired = User::factory()->create([
        'class' => 1, 'class_expire' => now()->subDay(),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 30 * 1024 ** 3, 'd' => 0,
    ]);
    $free = User::factory()->create([
        'class' => 0, 'class_expire' => null,
        'transfer_enable' => 0, 'u' => 5 * 1024 ** 3, 'd' => 0,
    ]);

    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    // 有效会员：配额不变，已用清零
    expect((int) $active->fresh()->u)->toBe(0);
    expect((int) $active->fresh()->d)->toBe(0);
    expect($active->fresh()->transfer_enable)->toBe(100 * 1024 ** 3);
    // 过期/免费用户：不动
    expect((int) $expired->fresh()->u)->toBe(30 * 1024 ** 3);
    expect((int) $free->fresh()->u)->toBe(5 * 1024 ** 3);
});
