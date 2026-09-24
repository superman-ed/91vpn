<?php

use App\Models\Node;
use App\Models\User;

it('serves clash config for a valid token', function () {
    $user = User::factory()->create([
        'invite_token' => 'VALIDTOKEN',
        'class' => 2, 'class_expire' => now()->addDays(10),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 0, 'd' => 0,
    ]);
    // `[!]` online 从 2026-09-24 起默认 false（迁移
    //   default_nodes_offline_until_heartbeat）—— 新建节点在 agent 首次上报前
    //   不进订阅。这里显式写成 true = "该节点的 agent 已经报到过",
    //   这是本用例一直隐含的前提,只是以前由一个会撒谎的默认值替它成立。
    Node::create(['name' => '香港01', 'server' => 'hk.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1', 'online' => true]);

    $res = $this->get('/sub/VALIDTOKEN');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('yaml');
    $res->assertSee('香港01');
    $res->assertSee($user->uuid);

    // 订阅记录写入类型(格式 flag)
    $log = \App\Models\SubscribeLog::where('user_id', $user->id)->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->type)->toBe('clash');   // 默认/clash UA → clash
});

it('records v2ray type when flag is requested', function () {
    $user = User::factory()->create(['invite_token' => 'FLAGTOKEN', 'class' => 2, 'class_expire' => now()->addDays(10), 'transfer_enable' => 100 * 1024 ** 3]);
    Node::create(['name' => '日本01', 'server' => 'jp.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's2']);

    $this->get('/sub/FLAGTOKEN?flag=v2ray')->assertOk();

    expect(\App\Models\SubscribeLog::where('user_id', $user->id)->latest('id')->first()->type)->toBe('v2ray');
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
