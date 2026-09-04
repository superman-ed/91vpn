<?php

use App\Models\Node;
use App\Models\User;
use App\Services\NodeUserService;

// 节点排空(enabled=false):即便 online=true(agent 心跳照写),也应从可服务名单/订阅/列表消失,
// 实现"先摘用户漏干、再停 agent"的优雅下线。解决心跳无条件写 online=true 无法排空的运维死结。

function drainUser(): User
{
    return User::factory()->create(['class' => 1, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3, 'u' => 0, 'd' => 0]);
}

it('排空节点(enabled=false)对 mod_mu 返回空名单,即便 online', function () {
    drainUser();
    $node = Node::create(['name' => 'drain', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'x', 'online' => true, 'enabled' => false]);

    expect(app(NodeUserService::class)->servableUsers($node))->toBe([]);
});

it('启用节点(enabled=true)正常返回可服务用户', function () {
    drainUser();
    $node = Node::create(['name' => 'ok', 'server' => 's', 'port' => 2, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'y', 'online' => true, 'enabled' => true]);

    expect(count(app(NodeUserService::class)->servableUsers($node)))->toBeGreaterThan(0);
});

it('排空节点不进用户订阅', function () {
    $u = drainUser();
    Node::create(['name' => 'ONNODE', 'server' => 's', 'port' => 3, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'a', 'online' => true, 'enabled' => true]);
    Node::create(['name' => 'DRAINNODE', 'server' => 's', 'port' => 4, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'b', 'online' => true, 'enabled' => false]);

    $yaml = app(\App\Services\SubscriptionService::class)->generate($u, 'clash');
    expect($yaml)->toContain('ONNODE');
    expect($yaml)->not->toContain('DRAINNODE');
});

it('排空节点不出现在 Web 用户面板与 App 节点列表', function () {
    apiUser();
    Node::create(['name' => 'SHOWN', 'server' => 's', 'port' => 5, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'c', 'online' => true, 'enabled' => true]);
    Node::create(['name' => 'HIDDEN', 'server' => 's', 'port' => 6, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'd', 'online' => true, 'enabled' => false]);

    // App
    $data = collect($this->getJson('/api/servers', ['Authorization' => 'Bearer TESTTOKEN123'])->json('data'));
    expect($data->pluck('name'))->toContain('SHOWN');
    expect($data->pluck('name'))->not->toContain('HIDDEN');
});
