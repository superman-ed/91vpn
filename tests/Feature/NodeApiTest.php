<?php

use App\Models\Node;
use App\Models\User;

function makeNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'NODESECRET',
    ], $attr));
}

it('rejects WebAPI without correct secret', function () {
    $node = makeNode();
    $this->getJson("/mod_mu/users?node_id={$node->id}&key=WRONG")->assertStatus(401);
});

it('returns servable users for a node', function () {
    $node = makeNode(['node_class' => 1]);
    $ok = User::factory()->create(['class' => 2, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3, 'u' => 0, 'd' => 0]);
    User::factory()->create(['class' => 0, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3]); // 等级不够
    User::factory()->create(['class' => 3, 'class_expire' => now()->subDay(), 'transfer_enable' => 1024 ** 3]); // 过期
    User::factory()->create(['class' => 3, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3, 'u' => 1024 ** 3, 'd' => 0]); // 流量耗尽

    $res = $this->getJson("/mod_mu/users?node_id={$node->id}&key=NODESECRET")->assertOk();
    $data = $res->json('users');

    expect($data)->toHaveCount(1);
    expect($data[0]['uuid'])->toBe($ok->uuid);
});

it('records traffic with node rate multiplier', function () {
    $node = makeNode(['traffic_rate' => 2]);   // 2倍率
    $user = User::factory()->create(['u' => 0, 'd' => 0]);

    $this->postJson("/mod_mu/users/traffic?node_id={$node->id}&key=NODESECRET", [
        'data' => [
            ['user_id' => $user->id, 'u' => 1000, 'd' => 2000],
        ],
    ])->assertOk();

    $user->refresh();
    expect($user->u)->toBe(2000);   // 1000 * 2
    expect($user->d)->toBe(4000);   // 2000 * 2
});

it('updates node heartbeat on ping', function () {
    $node = makeNode(['online' => false, 'last_heartbeat' => 0]);
    $this->getJson("/mod_mu/func/ping?node_id={$node->id}&key=NODESECRET")->assertOk();

    $node->refresh();
    expect($node->online)->toBeTrue();
    expect($node->last_heartbeat)->toBeGreaterThan(0);
});
