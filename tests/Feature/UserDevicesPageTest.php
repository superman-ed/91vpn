<?php

use App\Models\AliveIp;
use App\Models\Device;
use App\Models\Node;
use App\Models\User;

/**
 * 用户端「我的设备」页。
 *
 * `[!!]` 这一页此前只列 alive_ips（IP），标题却写「在线设备」、旁边挂着「设备上限」——
 * 而那个上限自 e379f3a 起管的是 devices 表里的设备数。两个不同的东西显示成同一个：
 * `[D]` 一台手机 Wi-Fi 切蜂窝会在 IP 里留两行、在设备里始终是一台，
 * 用户看到「在线 2 台 / 上限 3 台」会以为还能再加一台。
 */
function udUser(array $attr = []): User
{
    return User::factory()->create(array_merge(['node_ip_limit' => 3], $attr));
}

it('设备一栏读的是 devices 表，不是 IP', function () {
    $user = udUser();
    Device::create([
        'user_id' => $user->id, 'device_id' => 'dev-abc', 'platform' => 'android',
        'brand' => 'Xiaomi', 'model' => '14 Pro', 'app_version' => '1.2.0',
        'ip' => '1.1.1.1', 'last_seen' => now(),
    ]);

    $html = $this->actingAs($user)->get('/user/devices')->assertOk()->getContent();

    expect($html)->toContain('Xiaomi 14 Pro');
    expect($html)->toContain('1.2.0');
    expect($html)->toContain('套餐上限');
});

// `[!!]` 一台设备换 IP 不能让"设备数"变成 2 —— 那正是本次要消掉的误导。
it('一台设备两个 IP，设备数仍是 1', function () {
    $user = udUser();
    $node = Node::create(['name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'S']);

    Device::create(['user_id' => $user->id, 'device_id' => 'dev-1',
        'platform' => 'android', 'last_seen' => now()]);
    // 同一台手机切网留下的两行 IP
    foreach (['1.1.1.1', '2.2.2.2'] as $ip) {
        AliveIp::create(['user_id' => $user->id, 'node_id' => $node->id, 'ip' => $ip, 'last_seen' => now()]);
    }

    $html = $this->actingAs($user)->get('/user/devices')->assertOk()->getContent();

    expect($html)->toContain('已登记 <b class="">1</b> 台');
    // IP 那一栏照常显示两条，但明确标着不计入上限
    expect($html)->toContain('不计入设备上限');
});

// `[!]` 第三方客户端不带 device_id，在 devices 表里【不会出现】——
// 空白页比 IP 列表更糟，所以下半块必须留着，并且文案要说清楚。
it('没有登记设备时也要说明第三方客户端的情况', function () {
    $user = udUser();
    $html = $this->actingAs($user)->get('/user/devices')->assertOk()->getContent();

    expect($html)->toContain('还没有登记的设备');
    expect($html)->toContain('第三方客户端');
    expect($html)->toContain('同样可以正常使用');   // 别让用户以为自己坏了
});

it('不限设备数时不显示上限', function () {
    $user = udUser(['node_ip_limit' => 0]);
    $html = $this->actingAs($user)->get('/user/devices')->assertOk()->getContent();

    expect($html)->toContain('不限设备数');
    expect($html)->not->toContain('套餐上限');
});
