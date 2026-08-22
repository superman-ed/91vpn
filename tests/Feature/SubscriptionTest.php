<?php

use App\Models\Node;
use App\Models\User;

it('serves clash config for a valid token', function () {
    $user = User::factory()->create([
        'invite_token' => 'VALIDTOKEN',
        'class' => 2, 'class_expire' => now()->addDays(10),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 0, 'd' => 0,
    ]);
    Node::create(['name' => '香港01', 'server' => 'hk.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);

    $res = $this->get('/sub/VALIDTOKEN');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('yaml');
    $res->assertSee('香港01');
    $res->assertSee($user->uuid);
});

it('returns 404 for unknown token', function () {
    $this->get('/sub/NOSUCHTOKEN')->assertNotFound();
});

it('returns 403 for expired user token', function () {
    User::factory()->create([
        'invite_token' => 'EXPIREDTOKEN',
        'class' => 1, 'class_expire' => now()->subDay(),
        'transfer_enable' => 100 * 1024 ** 3,
    ]);

    $this->get('/sub/EXPIREDTOKEN')->assertStatus(403);
});

it('does not require authentication', function () {
    $user = User::factory()->create([
        'invite_token' => 'PUBLICTOKEN',
        'class' => 1, 'class_expire' => now()->addDays(5),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 0, 'd' => 0,
    ]);
    Node::create(['name' => '日本01', 'server' => 'jp.example.com', 'port' => 10087, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's2']);

    // 未登录也能访问
    $this->assertGuest();
    $this->get('/sub/PUBLICTOKEN')->assertOk();
});
