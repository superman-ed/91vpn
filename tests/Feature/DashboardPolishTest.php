<?php

use App\Models\User;

it('computes usage percent on dashboard', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDay(),
        'transfer_enable' => 100 * 1024 ** 3,
        'u' => 30 * 1024 ** 3, 'd' => 20 * 1024 ** 3,   // 已用 50%
    ]);
    $this->actingAs($user)->get('/user')->assertOk()->assertSee('50%');
});

it('shows checked-in state when already checked in today', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3,
        'last_check_in' => now()->timestamp,
    ]);
    $this->actingAs($user)->get('/user')->assertOk()->assertSee('今日已签到');
});

it('shows checkin button when not checked in today', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3,
        'last_check_in' => 0,
    ]);
    $this->actingAs($user)->get('/user')->assertOk()->assertSee('每日签到');
});
