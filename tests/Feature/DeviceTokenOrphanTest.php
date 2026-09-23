<?php

use App\Models\Device;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\DeviceService;

/**
 * `devices` 与 `device_tokens` 的接缝：删设备必须一并吊销它的登录凭证。
 *
 * `[!!]` 背景：两张表由两个并行会话分别建，靠 `(user_id, device_id)` 这对值【约定】关联。
 * `device_tokens.user_id` 有外键级联，但 `device_id` 是个裸 string，`Device` 也没有删除钩子。
 * `[D]` 2026-09-23 实测确认过孤儿路径成立，且孤儿 token【仍然能通过鉴权】。
 *
 * `[!]` 为什么不做数据库外键：登录时【并不建设备行】——
 * `AuthApiController::tokenFor()` 直接发 token，设备行要等客户端拉订阅或上报时才建。
 * 加外键会让登录当场失败。所以做在模型层。
 */
it('删设备时一并吊销它的 token', function () {
    $user = User::factory()->create();
    $device = Device::create(['user_id' => $user->id, 'device_id' => 'd1',
        'platform' => 'android', 'last_seen' => now()]);
    DeviceToken::issue($user, 'd1');

    $device->delete();

    expect(DeviceToken::where('device_id', 'd1')->exists())->toBeFalse();
});

// `[!!]` 真实触发路径：设备超 15 天未上报被自动回收 —— 不需要任何人操作。
// 这是本次修复前唯一的孤儿来源（手工移除那条路本来就删了两边）。
it('被自动回收的设备，其 token 也失效', function () {
    $user = User::factory()->create(['node_ip_limit' => 1]);
    $svc = app(DeviceService::class);

    $svc->admit($user, 'old-device', ['platform' => 'android']);
    $oldToken = DeviceToken::issue($user, 'old-device');
    Device::where('device_id', 'old-device')
        ->update(['last_seen' => now()->subDays(DeviceService::STALE_DAYS + 1)]);

    $svc->admit($user, 'new-device', ['platform' => 'windows']);   // 满额 → 回收老设备

    expect(Device::where('device_id', 'old-device')->exists())->toBeFalse();
    $this->withHeader('Authorization', 'Bearer '.$oldToken)
        ->getJson('/api/user')->assertUnauthorized();   // 401：凭证已吊销
});

// 不能误伤别的设备 —— 回收一台不该把这个账号登出
it('回收一台设备不影响其它设备的登录', function () {
    $user = User::factory()->create(['node_ip_limit' => 1]);
    $svc = app(DeviceService::class);

    $svc->admit($user, 'old-device', ['platform' => 'android']);
    DeviceToken::issue($user, 'old-device');
    Device::where('device_id', 'old-device')
        ->update(['last_seen' => now()->subDays(DeviceService::STALE_DAYS + 1)]);

    $svc->admit($user, 'new-device', ['platform' => 'windows']);
    $newToken = DeviceToken::issue($user, 'new-device');

    $this->withHeader('Authorization', 'Bearer '.$newToken)
        ->getJson('/api/user')->assertOk();
});

// `[!]` 如实钉住局限：Eloquent 模型事件【不管批量删】。
// 当前代码里没有这种删法；这条用例存在是为了让将来加的人知道要自己处理 token。
it('局限：批量删不触发钩子，token 会留下', function () {
    $user = User::factory()->create();
    Device::create(['user_id' => $user->id, 'device_id' => 'd1',
        'platform' => 'android', 'last_seen' => now()]);
    DeviceToken::issue($user, 'd1');

    Device::where('user_id', $user->id)->delete();   // 查询构造器，不经过模型

    expect(Device::where('device_id', 'd1')->exists())->toBeFalse();
    expect(DeviceToken::where('device_id', 'd1')->exists())->toBeTrue();   // 仍是孤儿
});
