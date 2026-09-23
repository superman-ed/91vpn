<?php

use App\Models\Device;
use App\Models\Node;
use App\Models\User;

// 一个能正常出订阅的用户(有效套餐+流量),device 上限由 node_ip_limit 决定
function subUser(int $limit, string $token = 'DEVTOKEN'): User
{
    $user = User::factory()->create([
        'invite_token' => $token,
        'class' => 2, 'class_expire' => now()->addDays(10),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 0, 'd' => 0,
        'node_ip_limit' => $limit,
    ]);
    Node::firstOrCreate(
        ['name' => '香港01'],
        ['server' => 'hk.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1'],
    );

    return $user;
}

it('registers a device on subscription pull within the limit', function () {
    $user = subUser(2);

    $this->get('/sub/DEVTOKEN?device_id=devA')->assertOk();
    $this->get('/sub/DEVTOKEN?device_id=devB')->assertOk();

    expect(Device::where('user_id', $user->id)->count())->toBe(2);
    expect(Device::where('user_id', $user->id)->pluck('device_id')->sort()->values()->all())->toBe(['devA', 'devB']);
});

it('refuses a new device over the limit with a 403 + message', function () {
    $user = subUser(2);
    $this->get('/sub/DEVTOKEN?device_id=devA')->assertOk();
    $this->get('/sub/DEVTOKEN?device_id=devB')->assertOk();

    // 超限 → 403(与现有 sub 错误一致,仅断言状态;文案投递见 report 路径的 JSON 断言)
    $this->get('/sub/DEVTOKEN?device_id=devC')->assertStatus(403);

    expect(Device::where('user_id', $user->id)->count())->toBe(2);   // 未登记第三台
});

it('lets a known device reconnect without consuming a new slot', function () {
    $user = subUser(2);
    $this->get('/sub/DEVTOKEN?device_id=devA')->assertOk();
    $this->get('/sub/DEVTOKEN?device_id=devB')->assertOk();

    // 已登记的 devA 再拉一次 → 放行,不新增
    $this->get('/sub/DEVTOKEN?device_id=devA')->assertOk();
    expect(Device::where('user_id', $user->id)->count())->toBe(2);
});

it('reclaims a stale device to admit a new one', function () {
    $user = subUser(1);
    // 一台 20 天没上报的陈旧设备占着唯一名额
    Device::create(['user_id' => $user->id, 'device_id' => 'old', 'platform' => 'android', 'last_seen' => now()->subDays(20)]);

    $this->get('/sub/DEVTOKEN?device_id=fresh')->assertOk();

    $ids = Device::where('user_id', $user->id)->pluck('device_id')->all();
    expect($ids)->toBe(['fresh']);   // 陈旧的被回收,新设备入场,仍是 1 台
});

it('does not reclaim a still-active device (stays refused)', function () {
    $user = subUser(1);
    Device::create(['user_id' => $user->id, 'device_id' => 'active', 'platform' => 'android', 'last_seen' => now()->subDay()]);

    $this->get('/sub/DEVTOKEN?device_id=fresh')->assertStatus(403);
    expect(Device::where('user_id', $user->id)->pluck('device_id')->all())->toBe(['active']);
});

it('does not enforce when limit is 0 (unlimited)', function () {
    $user = subUser(0);
    $this->get('/sub/DEVTOKEN?device_id=d1')->assertOk();
    $this->get('/sub/DEVTOKEN?device_id=d2')->assertOk();
    $this->get('/sub/DEVTOKEN?device_id=d3')->assertOk();
    expect(Device::where('user_id', $user->id)->count())->toBe(3);
});

it('skips enforcement when no device_id is provided (third-party/legacy clients)', function () {
    $user = subUser(1);
    Device::create(['user_id' => $user->id, 'device_id' => 'existing', 'platform' => 'android', 'last_seen' => now()]);

    // 不带 device_id → 照常出订阅,不登记新设备
    $this->get('/sub/DEVTOKEN')->assertOk();
    expect(Device::where('user_id', $user->id)->count())->toBe(1);
});

it('enforces the limit on device report too (403 ret:0)', function () {
    $user = subUser(1, 'RPTTOKEN');
    $user->update(['api_token' => 'BEARER1']);
    Device::create(['user_id' => $user->id, 'device_id' => 'active', 'platform' => 'android', 'last_seen' => now()]);

    $this->postJson('/api/device/report', [
        'device_id' => 'another', 'platform' => 'Android', 'model' => 'Redmi K60',
    ], ['Authorization' => 'Bearer BEARER1'])
        ->assertStatus(403)
        ->assertJson(['ret' => 0]);

    expect(Device::where('user_id', $user->id)->count())->toBe(1);   // 未登记第二台
});
