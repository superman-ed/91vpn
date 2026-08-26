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

it('authenticates via X-Node-Secret header (no key in query)', function () {
    $node = makeNode();
    $this->getJson("/mod_mu/users?node_id={$node->id}", ['X-Node-Secret' => 'NODESECRET'])->assertOk();
    $this->getJson("/mod_mu/users?node_id={$node->id}", ['X-Node-Secret' => 'WRONG'])->assertStatus(401);
    $this->getJson("/mod_mu/users?node_id={$node->id}")->assertStatus(401); // 无密钥
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

it('rejects negative traffic (no free continuation)', function () {
    $node = makeNode(['traffic_rate' => 1]);
    $user = User::factory()->create(['u' => 5000, 'd' => 5000]);

    $this->postJson("/mod_mu/users/traffic?node_id={$node->id}&key=NODESECRET", [
        'data' => [['user_id' => $user->id, 'u' => -9999, 'd' => 0]],
    ])->assertOk();

    $user->refresh();
    expect($user->u)->toBe(5000);   // 负数被拒,已用流量不被冲回
    expect($user->d)->toBe(5000);
});

it('ignores traffic for a user the node is not entitled to serve', function () {
    $node = makeNode(['node_class' => 3]);           // 高等级节点
    $low = User::factory()->create(['class' => 0, 'u' => 0, 'd' => 0]);   // 低等级用户
    $banned = User::factory()->create(['class' => 5, 'banned' => true, 'u' => 0, 'd' => 0]);

    $this->postJson("/mod_mu/users/traffic?node_id={$node->id}&key=NODESECRET", [
        'data' => [
            ['user_id' => $low->id, 'u' => 1000, 'd' => 1000],
            ['user_id' => $banned->id, 'u' => 1000, 'd' => 1000],
            ['user_id' => 999999, 'u' => 1000, 'd' => 1000],   // 不存在
        ],
    ])->assertOk();

    expect($low->fresh()->u)->toBe(0);      // 等级不够,不入账
    expect($banned->fresh()->u)->toBe(0);   // 已封禁,不入账
});

it('updates node heartbeat on ping', function () {
    $node = makeNode(['online' => false, 'last_heartbeat' => 0]);
    $this->getJson("/mod_mu/func/ping?node_id={$node->id}&key=NODESECRET")->assertOk();

    $node->refresh();
    expect($node->online)->toBeTrue();
    expect($node->last_heartbeat)->toBeGreaterThan(0);
});

it('mod_mu traffic endpoint is exempt from CSRF (real POST)', function () {
    $node = makeNode(['traffic_rate' => 1]);
    $user = User::factory()->create(['u' => 0, 'd' => 0]);

    // 用 post + 普通表单/json，模拟节点真实调用（无 CSRF token）
    $this->post("/mod_mu/users/traffic?node_id={$node->id}&key=NODESECRET",
        ['data' => [['user_id' => $user->id, 'u' => 100, 'd' => 200]]]
    )->assertOk();

    expect($user->fresh()->u)->toBe(100);
});
