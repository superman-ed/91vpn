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

it('accepts token via body as fallback', function () {
    $user = User::factory()->create(['api_token' => 'TOK2']);
    $this->postJson('/api/device/report', reportPayload(['token' => 'TOK2', 'device_id' => 'd2']))->assertOk();
    expect(Device::where('user_id', $user->id)->where('device_id', 'd2')->exists())->toBeTrue();
});

it('shows self-client device stats on the device page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Device::create(['user_id' => $admin->id, 'device_id' => 'x1', 'platform' => 'android', 'model' => 'Redmi K60', 'os_version' => '14', 'app_version' => '1.2.0', 'last_seen' => now()]);
    Device::create(['user_id' => $admin->id, 'device_id' => 'x2', 'platform' => 'ios', 'model' => 'iPhone 15 Pro', 'os_version' => '17.2', 'app_version' => '1.2.0', 'last_seen' => now()]);

    $res = $this->actingAs($admin)->get('/admin/system/devices');
    $res->assertOk()
        ->assertViewHas('deviceCount', 2)
        ->assertSee('自研客户端设备')
        ->assertSee('Redmi K60')
        ->assertSee('iPhone 15 Pro');
    expect($res->viewData('byAppVersion')->get('1.2.0'))->toBe(2);
});

it('shows the ready-placeholder when no devices reported yet', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/admin/system/devices')
        ->assertOk()->assertSee('接收框架已就绪');
});
