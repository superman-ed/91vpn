<?php

use App\Models\AliveIp;
use App\Models\Node;
use App\Models\User;
use App\Services\AliveIpService;

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

// `[!!]` 超限时踢谁：保留【最近还在活动的】，踢掉最久没动静的。
//
// `[D]` 此前按 id 升序「先到先得」，而 id 记的是【第一次见到这个 IP】。
// 真机撞到过：一台手机从 Wi-Fi 切到蜂窝留下两行，旧 IP 的 id 更小被保留，
// 用户此刻真正在用的那个新 IP 反而被踢（alive_ips 里 id=35 已 290 秒没动，
// id=36 是 1 秒前的当前连接）。手机进出 Wi-Fi 是每天很多次的事。
it('超限时踢掉最久没动静的，而不是最晚出现的', function () {
    $user = User::factory()->create(['node_ip_limit' => 1]);

    // 先建的是旧 IP（id 更小），但它已经很久没动
    $old = AliveIp::create([
        'user_id' => $user->id, 'ip' => '1.1.1.1', 'last_seen' => now()->subSeconds(90),
    ]);
    // 后建的是当前在用的（id 更大，last_seen 最新）
    $cur = AliveIp::create([
        'user_id' => $user->id, 'ip' => '2.2.2.2', 'last_seen' => now(),
    ]);
    expect($cur->id)->toBeGreaterThan($old->id);   // 前提：id 顺序确实是旧→新

    $blocked = app(AliveIpService::class)->blockedIps([$user->id]);

    expect($blocked)->toHaveCount(1);
    // 被踢的必须是旧的那个
    expect($blocked[0]['ips'])->toBe(['1.1.1.1']);
    expect($blocked[0]['ips'])->not->toContain('2.2.2.2');
});

// 没超限时不该踢任何人 —— 免得"改了排序"顺手把阈值也改坏
it('没超过上限时不踢任何人', function () {
    $user = User::factory()->create(['node_ip_limit' => 3]);
    foreach (['1.1.1.1', '2.2.2.2'] as $i => $ip) {
        AliveIp::create(['user_id' => $user->id, 'ip' => $ip, 'last_seen' => now()->subSeconds($i * 10)]);
    }

    expect(app(AliveIpService::class)->blockedIps([$user->id]))->toBe([]);
});

// `[!]` ip_limit=0 是"不限"，不是"一个都不给" —— 迁移默认值就是 0
it('ip_limit 为 0 时完全不限制', function () {
    $user = User::factory()->create(['node_ip_limit' => 0]);
    foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $ip) {
        AliveIp::create(['user_id' => $user->id, 'ip' => $ip, 'last_seen' => now()]);
    }

    expect(app(AliveIpService::class)->blockedIps([$user->id]))->toBe([]);
});
