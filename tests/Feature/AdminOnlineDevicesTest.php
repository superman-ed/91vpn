<?php

use App\Models\AliveIp;
use App\Models\Device;
use App\Models\Node;
use App\Models\User;

/**
 * 后台「在线用户」页：设备与 IP 必须分成两列。
 *
 * `[!!]` 此前这两件事合成一列、标题写「在线设备 / IP」、数的是去重 IP 而单位写「台」——
 * `[D]` 于是一台手机 Wi-Fi 切蜂窝就显示成「2 台」，而它明明只有一台设备。
 * 用户侧那一页已于同日修掉同样的问题（UserDevicesPageTest）。
 *
 * `[!]` 两者的数据源性质不同，不能互换：
 *   alive_ips  节点每分钟上报、120 秒窗口 → 这是"此刻在不在线"的唯一来源
 *   devices    拉订阅/客户端上报时更新     → 这是"有哪些设备"的身份记录
 */
function aodAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function aodNode(): Node
{
    return Node::create(['name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'AODSECRET']);
}

it('设备名单独成列，并显示机型', function () {
    $node = aodNode();
    $u = User::factory()->create();
    AliveIp::create(['user_id' => $u->id, 'node_id' => $node->id, 'ip' => '1.1.1.1', 'last_seen' => now()]);
    Device::create(['user_id' => $u->id, 'device_id' => 'd1', 'platform' => 'android',
        'brand' => 'HUAWEI', 'model' => 'CET-AL00', 'app_version' => '0.1.0',
        'ip' => '1.1.1.1', 'last_seen' => now()]);

    $html = $this->actingAs(aodAdmin())->get('/admin/online')->assertOk()->getContent();

    expect($html)->toContain('HUAWEI CET-AL00');
    expect($html)->toContain('已登记设备');
    expect($html)->toContain('在线 IP');
});

// `[!!]` 一台设备换 IP 不能显示成两台 —— 这正是此前那一列的毛病。
it('一台设备两个在线 IP，设备列仍只有一台', function () {
    $node = aodNode();
    $u = User::factory()->create();
    foreach (['1.1.1.1', '2.2.2.2'] as $ip) {
        AliveIp::create(['user_id' => $u->id, 'node_id' => $node->id, 'ip' => $ip, 'last_seen' => now()]);
    }
    Device::create(['user_id' => $u->id, 'device_id' => 'd1', 'platform' => 'android',
        'brand' => 'HUAWEI', 'model' => 'CET-AL00', 'ip' => '1.1.1.1', 'last_seen' => now()]);

    $html = $this->actingAs(aodAdmin())->get('/admin/online')->assertOk()->getContent();

    expect(substr_count($html, 'HUAWEI CET-AL00'))->toBe(1);   // 设备只出现一次
    expect($html)->toContain('2 个');                           // IP 是 2 个，单位是「个」不是「台」
});

// `[!]` 第三方客户端不带 device_id，devices 里不会有记录 ——
// 但用户【确实在线】。这一格必须说清楚，否则后台会以为是数据丢了。
it('在线但没有登记设备时说明是没用官方客户端', function () {
    $node = aodNode();
    $u = User::factory()->create();
    AliveIp::create(['user_id' => $u->id, 'node_id' => $node->id, 'ip' => '3.3.3.3', 'last_seen' => now()]);

    $html = $this->actingAs(aodAdmin())->get('/admin/online')->assertOk()->getContent();

    expect($html)->toContain('未用官方客户端');
});
