<?php

use App\Models\AliveIp;
use App\Models\Node;
use App\Models\User;

function ipLimitNode(): Node
{
    return Node::create([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'NODESECRET',
    ]);
}

it('超限时不再返回 blocked —— 已停用按 IP 踢人', function () {
    $node = ipLimitNode();
    $user = User::factory()->create(['node_ip_limit' => 2]);

    $res = $this->postJson("/mod_mu/users/aliveip?node_id={$node->id}&key=NODESECRET", [
        'data' => [
            ['user_id' => $user->id, 'ip' => '1.1.1.1'],
            ['user_id' => $user->id, 'ip' => '2.2.2.2'],
            ['user_id' => $user->id, 'ip' => '3.3.3.3'],   // 第 3 个，超限
        ],
    ])->assertOk();

    // `[!!]` 2026-09-23 起【不再按 IP 踢人】—— 额度改按设备算（DeviceService）。
    //   本条从"验踢谁"变成"验一个都不踢"。判据本身仍由 AliveIpTest 直接调
    //   blockedIps() 守着（那个方法保留但已无调用方）。
    expect($res->json('blocked'))->toBe([]);
    // 记录照记:alive_ips 仍是共享检测的线索与"异常登录"展示的来源
    expect(AliveIp::where('user_id', $user->id)->count())->toBe(3);
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
