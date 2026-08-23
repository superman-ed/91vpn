<?php

use App\Models\Node;
use App\Models\User;

function ipLimitNode(): Node
{
    return Node::create([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'NODESECRET',
    ]);
}

it('returns over-limit ips to block, keeping the earliest', function () {
    $node = ipLimitNode();
    $user = User::factory()->create(['node_ip_limit' => 2]);

    $res = $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=NODESECRET", [
        'data' => [
            ['user_id' => $user->id, 'ip' => '1.1.1.1'],
            ['user_id' => $user->id, 'ip' => '2.2.2.2'],
            ['user_id' => $user->id, 'ip' => '3.3.3.3'],   // 第 3 个，超限
        ],
    ])->assertOk();

    $blocked = $res->json('blocked');
    expect($blocked)->toHaveCount(1);
    expect($blocked[0]['user_id'])->toBe($user->id);
    expect($blocked[0]['ips'])->toBe(['3.3.3.3']);   // 先到先得，踢最后来的
});

it('blocks nothing when under the limit', function () {
    $node = ipLimitNode();
    $user = User::factory()->create(['node_ip_limit' => 3]);

    $res = $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=NODESECRET", [
        'data' => [
            ['user_id' => $user->id, 'ip' => '1.1.1.1'],
            ['user_id' => $user->id, 'ip' => '2.2.2.2'],
        ],
    ])->assertOk();

    expect($res->json('blocked'))->toBe([]);
});

it('never blocks when ip_limit is 0 (unlimited)', function () {
    $node = ipLimitNode();
    $user = User::factory()->create(['node_ip_limit' => 0]);

    $res = $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=NODESECRET", [
        'data' => [
            ['user_id' => $user->id, 'ip' => '1.1.1.1'],
            ['user_id' => $user->id, 'ip' => '2.2.2.2'],
            ['user_id' => $user->id, 'ip' => '3.3.3.3'],
        ],
    ])->assertOk();

    expect($res->json('blocked'))->toBe([]);
});
