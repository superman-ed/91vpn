<?php

use App\Models\Node;
use App\Models\User;

/**
 * L-08 消费端验证：「在线设备数」到底是什么，以及谁按它做了什么。
 *
 * `[!!]` 同 AuditL13WireTest 的写法：不从代码推，走真字节 + 真端点。
 * 这条链的消费端有【两个】，都不在服务层里：
 *   节点  —— 按响应里的 blocked 列表去踢连接
 *   用户  —— 面板上那个「在线设备 N / 上限」
 * 之前的 P22 实验直接调服务层，量的是中间态，不是这两个消费端看到的东西。
 */

/** agent 上报在线 IP 时实际发出的字节（sogacore: TestAliveIPWireBytes 的 WIRE）。 */
function l08Wire(int $userId, array $ips): string
{
    return json_encode(['data' => array_map(
        fn (string $ip) => ['ip' => $ip, 'user_id' => $userId], $ips
    )]);
}

function l08Node(): Node
{
    static $seq = 0;
    $seq++;

    return Node::create([
        'name' => 'L08-'.$seq, 'server' => '10.8.0.'.$seq, 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'node_class' => 0, 'traffic_rate' => 1,
        'secret' => 'L08SEC'.$seq, 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ]);
}

/** 走真实端点上报，返回节点收到的响应（blocked 就在里面）。 */
function l08Report(object $t, Node $node, string $body): array
{
    $server = [
        'HTTP_X_NODE_ID' => (string) $node->id,
        'HTTP_X_NODE_SECRET' => $node->secret,
        'CONTENT_TYPE' => 'application/json',
    ];
    $res = $t->call('POST', '/mod_mu/users/aliveip', [], [], [], $server, $body);
    // 前置断言收在这里 —— 端点没通时后面的结论全是空真（L-13 那次就栽在这）
    expect($res->status())->toBe(200);

    return $res->json();
}

// `[!!]` 本条【原本是在记录缺陷】：切换 IP 后被踢的是用户此刻正在用的那个。
// 2026-09-23 已修（AliveIpService 改为按 last_seen 倒序保留），断言随之翻转 ——
// 保留它作为回归守卫：改回「先到先得」这里就会红。
// 旧结论见 git 历史与清单 L-08。
it('L08-1 节点收到的 blocked 列表里，是用户【已经不在用】的那个 IP', function () {
    $node = l08Node();
    $user = User::factory()->create(['node_ip_limit' => 1, 'class' => 0]);

    // 手机在蜂窝网络上，先是 IP-A
    $r1 = l08Report($this, $node, l08Wire($user->id, ['203.0.113.1']));
    expect($r1['blocked'])->toBe([]);          // 只有一个，没超限

    // 一分钟后切到 IP-B（同一台手机，连接迁移）
    $this->travel(60)->seconds();
    $r2 = l08Report($this, $node, l08Wire($user->id, ['203.0.113.2']));

    // `[!!]` 这就是节点【实际会去执行】的指令。
    expect($r2['blocked'])->toHaveCount(1);
    $ips = $r2['blocked'][0]['ips'] ?? [];
    expect($ips)->toBe(['203.0.113.1']);       // 被踢的是已经不在用的旧 IP
    expect($ips)->not->toBe(['203.0.113.2']);  // 当前正在用的那个被保留
});

it('L08-2 用户面板上那个数字，同一时刻显示「2 台设备」', function () {
    $node = l08Node();
    $user = User::factory()->create(['node_ip_limit' => 1, 'class' => 0]);

    l08Report($this, $node, l08Wire($user->id, ['203.0.113.1']));
    $this->travel(60)->seconds();
    l08Report($this, $node, l08Wire($user->id, ['203.0.113.2']));

    // 消费端二：用户自己看到的页面
    $this->actingAs($user);
    $html = $this->get('/user')->assertOk()->getContent();

    expect($user->fresh()->onlineDevices())->toBe(2);
    // 页面上确实把它当「设备」在说
    expect($html)->toContain('2 / 1');
});

it('L08-3 反过来：一个 IP 后面几台设备，节点和面板都只算一台', function () {
    $node = l08Node();
    $user = User::factory()->create(['node_ip_limit' => 1, 'class' => 0]);

    // 家里三台设备共用宽带出口 —— 上报里就是同一个 IP 三次
    $r = l08Report($this, $node, l08Wire($user->id, ['203.0.113.9', '203.0.113.9', '203.0.113.9']));

    expect($r['blocked'])->toBe([]);                 // 没人被踢
    expect($user->fresh()->onlineDevices())->toBe(1);  // 面板也说 1 台

    // `[!!]` 两条合起来看才完整：设备数上限这个承诺，
    // 对共用出口的人【管不住】，对换 IP 的人【误伤】。
    // 而两种情况下系统都没有任何信息能知道自己数错了 ——
    // agent 线上根本没有设备/会话/连接维度的字段（sogacore: TestAliveIPWireBytes）。
});

it('L08-4 对照：真的有两个不同 IP 在用时，踢掉后来的那个是对的', function () {
    $node = l08Node();
    $user = User::factory()->create(['node_ip_limit' => 1, 'class' => 0]);

    // 两个 IP 【同时】保持活跃 —— 这才是"两台设备"的真实形态
    l08Report($this, $node, l08Wire($user->id, ['203.0.113.1']));
    $this->travel(60)->seconds();
    $r = l08Report($this, $node, l08Wire($user->id, ['203.0.113.1', '203.0.113.2']));

    expect($r['blocked'][0]['ips'])->toBe(['203.0.113.2']);

    // `[!]` 有这条对照才说明问题出在【哪里】：
    // 「先到先得」这个策略本身没错，错的是它分不清
    // "旧 IP 还在用" 和 "旧 IP 只是还没过期"。
    // 两种情况在 alive_ips 里长得一模一样，区别只在 last_seen，
    // 而排序用的是 id。
});
