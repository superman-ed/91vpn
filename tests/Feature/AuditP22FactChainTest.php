<?php

use App\Models\AliveIp;
use App\Models\Node;
use App\Models\User;
use App\Services\AliveIpService;

/**
 * 审计实验 P2-2 端到端事实链 —— 只做观察，不修复。
 *
 * 审的是【界面上的一个数字，能不能从源头一路追到显示，中间不断】。
 *
 * 本文件追的是「在线设备数」这条链：
 *     客户端连接 → 节点上报 (user_id, ip) → alive_ips → onlineDevices()
 *                                          → node_ip_limit 判超限 → 踢下线
 *
 * `[!!]` 链上每一跳都能跑通，而**被测量的东西**在第二跳换了：
 * 上报的是 IP，展示和限制用的名字是「设备」。
 * 这类断裂不会报错，也不会被任何单跳的测试发现。
 */
$GLOBALS['p22'] = 0;

function p22Node(): Node
{
    $i = ++$GLOBALS['p22'];

    return Node::create([
        'name' => 'P22-'.$i, 'server' => '10.22.0.'.$i, 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'node_class' => 0, 'traffic_rate' => 1.0,
        'secret' => 'P22'.$i, 'role' => 'landing', 'enabled' => true, 'online' => true,
    ]);
}

it('P22-A 同一出口 IP 后面的多台设备，只算一台', function () {
    $node = p22Node();
    $user = User::factory()->create(['node_ip_limit' => 2]);

    // 家里三台设备,共用一个宽带出口 —— 节点看到的是同一个 IP
    app(AliveIpService::class)->record($node, [
        ['user_id' => $user->id, 'ip' => '203.0.113.9'],
        ['user_id' => $user->id, 'ip' => '203.0.113.9'],
        ['user_id' => $user->id, 'ip' => '203.0.113.9'],
    ]);

    // 界面写「在线设备」,而实际数出来的是 IP 数
    expect($user->fresh()->onlineDevices())->toBe(1);
    expect(AliveIp::where('user_id', $user->id)->count())->toBe(1);
});

it('P22-B 一台设备换了 IP，就变成两台 —— 而被踢下线的是【正在用的那个】', function () {
    $node = p22Node();
    $user = User::factory()->create(['node_ip_limit' => 1]);
    $svc = app(AliveIpService::class);

    // 手机在蜂窝网络上,先是 IP-A
    $svc->record($node, [['user_id' => $user->id, 'ip' => '203.0.113.1']]);
    // 一分钟后切换到 IP-B（同一台手机，同一条连接迁移）
    $this->travel(60)->seconds();
    $svc->record($node, [['user_id' => $user->id, 'ip' => '203.0.113.2']]);

    // IP-A 的 last_seen 停在 60 秒前，仍落在 120 秒的在线窗口内
    expect($user->fresh()->onlineDevices())->toBe(2);

    $blocked = $svc->blockedIps([$user->id]);
    expect($blocked)->toHaveCount(1);

    // `[!!]` 要害在这里：「先到先得」按 alive_ips.id 升序保留，
    // 而 id 是【第一次见到这个 IP】时分配的。
    // 于是保留下来的是已经不在用的 IP-A，被踢的是用户此刻真正在用的 IP-B。
    expect($blocked[0]['ips'])->toBe(['203.0.113.2']);
    expect($blocked[0]['ips'])->not->toBe(['203.0.113.1']);

    // 按 last_seen 排序就不会错 —— 两行的 last_seen 差了 60 秒，信息是有的
    $rows = AliveIp::where('user_id', $user->id)->orderByDesc('last_seen')->pluck('ip');
    expect($rows->first())->toBe('203.0.113.2');
});

it('P22-B2 该状态最长持续到旧 IP 掉出在线窗口（120 秒）', function () {
    $node = p22Node();
    $user = User::factory()->create(['node_ip_limit' => 1]);
    $svc = app(AliveIpService::class);

    $svc->record($node, [['user_id' => $user->id, 'ip' => '203.0.113.1']]);
    $this->travel(60)->seconds();
    $svc->record($node, [['user_id' => $user->id, 'ip' => '203.0.113.2']]);
    expect($svc->blockedIps([$user->id]))->toHaveCount(1);

    // 再过 61 秒，旧 IP 超出 ONLINE_WINDOW，不再参与判定
    $this->travel(61)->seconds();
    $svc->record($node, [['user_id' => $user->id, 'ip' => '203.0.113.2']]);
    expect($svc->blockedIps([$user->id]))->toBe([]);

    // 也就是说影响是【有界的】：ip_limit=1 的用户每换一次 IP，
    // 最多被踢约两分钟。不是永久故障，但也不是无感。
});

it('P22-C 在线 IP 上报不做归属校验 —— 与流量上报的口径不一致', function () {
    $node = p22Node();
    $banned = User::factory()->create(['banned' => true, 'class' => 0]);

    // 流量上报会拒收被封用户的记录（见 P11-B）
    $n = app(\App\Services\TrafficService::class)->record($node, [
        ['user_id' => $banned->id, 'u' => 1000, 'd' => 1000],
    ]);
    expect($n)->toBe(0);

    // 而在线 IP 上报照单全收
    $c = app(AliveIpService::class)->record($node, [
        ['user_id' => $banned->id, 'ip' => '203.0.113.7'],
    ]);
    expect($c)->toBe(1);
    expect($banned->fresh()->onlineDevices())->toBe(1);

    // `[!]` 同一份上报里的两类数据，一类查归属一类不查。
    // 后果不是白嫖流量（那条堵住了），是【审计与设备数记录可被污染】——
    // 而 mod_mu 控制器里为中转专门写了这条理由，落地节点这边没走同一套。
});
