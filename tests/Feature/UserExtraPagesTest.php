<?php

use App\Models\SubscribeLog;
use App\Models\User;

it('shows downloads page', function () {
    $this->actingAs(User::factory()->create())->get('/user/downloads')
        ->assertOk()->assertSee('客户端下载');
});

it('records a subscribe log when subscription fetched', function () {
    $user = User::factory()->create([
        'invite_token' => 'SUBLOGTOKEN', 'class' => 1, 'class_expire' => now()->addDay(),
        'transfer_enable' => 1024**3, 'u' => 0, 'd' => 0,
    ]);
    $this->get('/sub/SUBLOGTOKEN')->assertOk();
    expect(SubscribeLog::where('user_id', $user->id)->exists())->toBeTrue();
});

it('shows subscribe records page', function () {
    $user = User::factory()->create();
    SubscribeLog::create(['user_id' => $user->id, 'ip' => '9.9.9.9', 'client' => 'Clash', 'fetched_at' => now()]);
    $this->actingAs($user)->get('/user/subscribe-log')->assertOk()->assertSee('9.9.9.9');
});
