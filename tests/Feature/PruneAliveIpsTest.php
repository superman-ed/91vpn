<?php

use App\Models\AliveIp;
use App\Models\User;

it('prunes only expired alive ips', function () {
    $user = User::factory()->create();
    AliveIp::create(['user_id' => $user->id, 'ip' => '1.1.1.1', 'last_seen' => now()->subSeconds(10)]);   // 在线
    AliveIp::create(['user_id' => $user->id, 'ip' => '2.2.2.2', 'last_seen' => now()->subSeconds(300)]);  // 过期

    $this->artisan('alive-ips:prune')->assertSuccessful();

    expect(AliveIp::count())->toBe(1);
    expect(AliveIp::where('ip', '1.1.1.1')->exists())->toBeTrue();
});
