<?php

use App\Models\Node;
use App\Models\User;

it('lists all online nodes for display (web is view-only, no class filter)', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay(), 'transfer_enable' => 1024**3, 'u'=>0,'d'=>0]);
    Node::create(['name' => '香港01', 'server' => 'hk.x.com', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1', 'online' => true]);
    Node::create(['name' => '高级节点', 'server' => 'x.com', 'port' => 2, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 2, 'node_class' => 3, 'secret' => 's2', 'online' => true]);
    Node::create(['name' => '离线节点', 'server' => 'off.com', 'port' => 3, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's3', 'online' => false]);

    $res = $this->actingAs($user)->get('/user/servers');
    // 全量展示:非会员也能看到高等级节点(网页仅展示、连不上,不按等级过滤/加锁);离线的不列
    $res->assertOk()->assertSee('香港01')->assertSee('高级节点')->assertDontSee('离线节点');
});

it('shows traffic detail page with daily records', function () {
    $user = User::factory()->create();
    \App\Models\DailyTraffic::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'u' => 1024**3, 'd' => 2*1024**3]);
    $this->actingAs($user)->get('/user/traffic')->assertOk()->assertSee('流量明细');
});
