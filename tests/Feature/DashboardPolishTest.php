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

it('shows 未开通 traffic for a user without a plan', function () {
    $user = User::factory()->create([
        'class' => 0, 'class_expire' => null, 'transfer_enable' => 0, 'u' => 0, 'd' => 0,
    ]);
    $this->actingAs($user)->get('/user')->assertOk()
        ->assertSee('暂无流量套餐')->assertDontSee('已用 ');
});

it('shows 已用尽 when traffic is exhausted', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDay(),
        'transfer_enable' => 10 * 1024 ** 3, 'u' => 10 * 1024 ** 3, 'd' => 0,
    ]);
    $this->actingAs($user)->get('/user')->assertOk()->assertSee('已用尽');
});

it('shows membership remaining days instead of vip tier', function () {
    $user = User::factory()->create([
        'class' => 2, 'class_expire' => now()->addDays(30), 'transfer_enable' => 1024 ** 3,
    ]);
    $this->actingAs($user)->get('/user')->assertOk()
        ->assertSee('会员时长')->assertSee('剩余 30 天');
});

it('shows 未开通 for a brand-new user who never subscribed', function () {
    $user = User::factory()->create([
        'class' => 0, 'class_expire' => null, 'transfer_enable' => 1024 ** 3,
    ]);
    $this->actingAs($user)->get('/user')->assertOk()->assertSee('未开通')->assertDontSee('已到期');
});

it('shows 已到期 for a user whose plan lapsed', function () {
    $user = User::factory()->create([
        'class' => 2, 'class_expire' => now()->subDay(), 'transfer_enable' => 1024 ** 3,
    ]);
    $this->actingAs($user)->get('/user')->assertOk()
        ->assertSee('已到期')->assertDontSee('未开通')->assertDontSee('剩余 ');
});

it('shows latest published announcements on dashboard', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3]);
    \App\Models\Announcement::create(['title' => '系统维护通知', 'content' => '今晚维护', 'published' => true]);
    \App\Models\Announcement::create(['title' => '隐藏公告', 'content' => 'x', 'published' => false]);

    $this->actingAs($user)->get('/user')->assertOk()
        ->assertSee('公告')->assertSee('系统维护通知')->assertDontSee('隐藏公告');
});

it('shows accumulated rebate total on wallet card', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3,
    ]);
    \App\Models\Payback::create(['user_id' => $user->id, 'from_user_id' => $user->id, 'amount' => 100.50]);
    \App\Models\Payback::create(['user_id' => $user->id, 'from_user_id' => $user->id, 'amount' => 30.09]);
    $this->actingAs($user)->get('/user')->assertOk()->assertSee('累计获得返利 130.59 元');
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
