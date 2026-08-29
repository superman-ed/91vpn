<?php

use App\Models\Device;
use App\Models\User;

function reportPayload(array $o = []): array
{
    return array_merge([
        'device_id' => 'dev-abc-123',
        'platform' => 'Android',
        'brand' => 'Xiaomi',
        'model' => 'Redmi K60',
        'os_version' => '14',
        'app_version' => '1.2.0',
    ], $o);
}

it('rejects device report without a valid api_token', function () {
    $this->postJson('/api/device/report', reportPayload())->assertStatus(401);
    expect(Device::count())->toBe(0);
});

it('upserts a device on report with bearer api_token', function () {
    $user = User::factory()->create(['api_token' => 'TOKEN123']);

    $this->postJson('/api/device/report', reportPayload(), ['Authorization' => 'Bearer TOKEN123'])
        ->assertOk()->assertJson(['ret' => 1]);

    $d = Device::where('user_id', $user->id)->first();
    expect($d->device_id)->toBe('dev-abc-123');
    expect($d->platform)->toBe('android');           // 归一化小写
    expect($d->model)->toBe('Redmi K60');
    expect($d->os_version)->toBe('14');
    expect($d->last_seen)->not->toBeNull();

    // 同设备再报 → 更新不新增
    $this->postJson('/api/device/report', reportPayload(['app_version' => '1.3.0']), ['Authorization' => 'Bearer TOKEN123'])->assertOk();
    expect(Device::where('user_id', $user->id)->count())->toBe(1);
    expect($d->fresh()->app_version)->toBe('1.3.0');
});

it('rejects token passed in body (统一为 Bearer 头认证,不再支持 body token)', function () {
    User::factory()->create(['api_token' => 'TOK2']);
    // 迁到 client.token 中间件后只认标准 Authorization: Bearer 头,body 里的 token 无效
    $this->postJson('/api/device/report', reportPayload(['token' => 'TOK2', 'device_id' => 'd2']))->assertStatus(401);
});

it('shows self-client device stats on the device page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Device::create(['user_id' => $admin->id, 'device_id' => 'x1', 'platform' => 'android', 'model' => 'Redmi K60', 'os_version' => '14', 'app_version' => '1.2.0', 'last_seen' => now()]);
    Device::create(['user_id' => $admin->id, 'device_id' => 'x2', 'platform' => 'ios', 'model' => 'iPhone 15 Pro', 'os_version' => '17.2', 'app_version' => '1.2.0', 'last_seen' => now()]);

    $res = $this->actingAs($admin)->get('/admin/system/devices');
    $res->assertOk()
        ->assertViewHas('deviceCount', 2)
        ->assertSee('平台分布')
        ->assertSee('Redmi K60')
        ->assertSee('iPhone 15 Pro');
    expect($res->viewData('byPlatform')->get('android'))->toBe(1);
    expect($res->viewData('byPlatform')->get('ios'))->toBe(1);
    expect($res->viewData('byAppVersion')->get('1.2.0'))->toBe(2);
});

it('shows the ready-placeholder when no devices reported yet', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/admin/system/devices')
        ->assertOk()->assertSee('接收框架已就绪');
});

it('backfills promo attribution from channel-package report', function () {
    \App\Models\PromoChannel::create(['code' => 'ZHANGSAN', 'name' => '张三', 'enabled' => true]);
    $user = User::factory()->create(['api_token' => 'TOKp', 'promo_code' => null]);

    $this->postJson('/api/device/report', reportPayload(['promo_code' => 'zhangsan']), ['Authorization' => 'Bearer TOKp'])->assertOk();
    expect($user->fresh()->promo_code)->toBe('ZHANGSAN');   // 归一化大写、回填
});

it('does not overwrite an existing promo attribution', function () {
    \App\Models\PromoChannel::create(['code' => 'A', 'name' => 'a', 'enabled' => true]);
    \App\Models\PromoChannel::create(['code' => 'B', 'name' => 'b', 'enabled' => true]);
    $user = User::factory()->create(['api_token' => 'TOKq', 'promo_code' => 'A']);

    $this->postJson('/api/device/report', reportPayload(['promo_code' => 'B']), ['Authorization' => 'Bearer TOKq'])->assertOk();
    expect($user->fresh()->promo_code)->toBe('A');   // 首次来源不被覆盖
});

it('ignores an invalid promo code on report', function () {
    $user = User::factory()->create(['api_token' => 'TOKr', 'promo_code' => null]);
    $this->postJson('/api/device/report', reportPayload(['promo_code' => 'NOPE']), ['Authorization' => 'Bearer TOKr'])->assertOk();
    expect($user->fresh()->promo_code)->toBeNull();
});
