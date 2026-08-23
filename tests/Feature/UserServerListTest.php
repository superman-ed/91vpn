<?php

use App\Models\Node;
use App\Models\User;

it('lists nodes the user can access', function () {
    $user = User::factory()->create(['class' => 2, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024**3, 'u'=>0,'d'=>0]);
    Node::create(['name' => '香港01', 'server' => 'hk.x.com', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1', 'online' => true]);
    Node::create(['name' => '高级节点', 'server' => 'x.com', 'port' => 2, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 2, 'node_class' => 3, 'secret' => 's2', 'online' => true]);

    $res = $this->actingAs($user)->get('/user/servers');
    $res->assertOk()->assertSee('香港01')->assertDontSee('高级节点'); // class3 看不到
});

it('shows traffic detail page with daily records', function () {
    $user = User::factory()->create();
    \App\Models\DailyTraffic::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'u' => 1024**3, 'd' => 2*1024**3]);
    $this->actingAs($user)->get('/user/traffic')->assertOk()->assertSee('流量明细');
});
