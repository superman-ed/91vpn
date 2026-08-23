<?php

use App\Models\User;

it('resets transfer_today for all users but keeps totals', function () {
    $a = User::factory()->create(['transfer_today' => 5 * 1024 ** 3, 'u' => 10 * 1024 ** 3, 'd' => 0]);
    $b = User::factory()->create(['transfer_today' => 0]);

    $this->artisan('traffic:reset-daily')->assertSuccessful();

    expect($a->fresh()->transfer_today)->toBe(0);
    expect($b->fresh()->transfer_today)->toBe(0);
    // 累计已用不受影响
    expect((int) $a->fresh()->u)->toBe(10 * 1024 ** 3);
});
