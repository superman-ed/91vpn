<?php

use App\Models\AliveIp;
use App\Models\Node;
use App\Models\User;

function aliveNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'NODESECRET',
    ], $attr));
}

it('records alive ips and counts online devices', function () {
    $node = aliveNode();
    $user = User::factory()->create();

    $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=NODESECRET", [
        'data' => [
            ['user_id' => $user->id, 'ip' => '1.1.1.1'],
            ['user_id' => $user->id, 'ip' => '2.2.2.2'],
        ],
    ])->assertOk()->assertJson(['ret' => 1, 'count' => 2]);

    expect($user->onlineDevices())->toBe(2);
});

it('dedupes the same ip for a user', function () {
    $node = aliveNode();
    $user = User::factory()->create();

    $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=NODESECRET", [
        'data' => [['user_id' => $user->id, 'ip' => '1.1.1.1']],
    ])->assertOk();
    $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=NODESECRET", [
        'data' => [['user_id' => $user->id, 'ip' => '1.1.1.1']],
    ])->assertOk();

    expect(AliveIp::where('user_id', $user->id)->count())->toBe(1);
    expect($user->onlineDevices())->toBe(1);
});

it('does not count ips outside the online window', function () {
    $user = User::factory()->create();
    AliveIp::create(['user_id' => $user->id, 'ip' => '1.1.1.1', 'last_seen' => now()->subSeconds(10)]);
    AliveIp::create(['user_id' => $user->id, 'ip' => '2.2.2.2', 'last_seen' => now()->subSeconds(300)]); // 过期

    expect($user->onlineDevices())->toBe(1);
});

it('rejects aliveip report without correct secret', function () {
    $node = aliveNode();
    $user = User::factory()->create();

    $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=WRONG", [
        'data' => [['user_id' => $user->id, 'ip' => '1.1.1.1']],
    ])->assertStatus(401);
});

it('shows real online device count on dashboard', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3,
        'node_ip_limit' => 3,
    ]);
    AliveIp::create(['user_id' => $user->id, 'ip' => '1.1.1.1', 'last_seen' => now()]);
    AliveIp::create(['user_id' => $user->id, 'ip' => '2.2.2.2', 'last_seen' => now()]);

    $this->actingAs($user)->get('/user')->assertOk()->assertSee('2 / 3');
});
