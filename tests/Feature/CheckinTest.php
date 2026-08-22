<?php

use App\Models\User;

it('adds traffic on check-in', function () {
    $user = User::factory()->create(['transfer_enable' => 1024 ** 3, 'last_check_in' => 0]);

    $this->actingAs($user)->post('/user/checkin')->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->transfer_enable)->toBeGreaterThan(1024 ** 3);
    expect($fresh->last_check_in)->toBeGreaterThan(0);
});

it('prevents double check-in same day', function () {
    $user = User::factory()->create(['transfer_enable' => 1024 ** 3, 'last_check_in' => now()->timestamp]);
    $before = $user->transfer_enable;

    $this->actingAs($user)->post('/user/checkin')->assertRedirect();

    expect($user->fresh()->transfer_enable)->toBe($before);
});
