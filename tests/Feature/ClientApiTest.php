<?php

use App\Models\User;

function apiUser(array $attr = []): User
{
    return User::factory()->create(array_merge([
        'email' => 'c@test.local', 'password' => 'secret1234', 'api_token' => 'TESTTOKEN123',
        'invite_token' => 'SUBTOKEN32', 'class' => 1, 'class_expire' => now()->addMonth(),
        'transfer_enable' => 10 * 1024 ** 3, 'u' => 1 * 1024 ** 3, 'd' => 2 * 1024 ** 3,
        'money' => 12.5, 'banned' => false,
    ], $attr));
}

// ---- 登录 ----

it('logs in with correct credentials and returns token + user info', function () {
    apiUser();
    $res = $this->postJson('/api/auth/login', ['email' => 'c@test.local', 'password' => 'secret1234'])->assertOk();
    $res->assertJsonPath('ret', 1)
        ->assertJsonPath('data.token', 'TESTTOKEN123')
        ->assertJsonPath('data.user.email', 'c@test.local')
        ->assertJsonPath('data.user.class', 1)
        ->assertJsonPath('data.user.sub_token', 'SUBTOKEN32');
    expect($res->json('data.user.transfer_remaining'))->toBe(7 * 1024 ** 3);   // 10 -(1+2)
});

it('rejects login with wrong password', function () {
    apiUser();
    $this->postJson('/api/auth/login', ['email' => 'c@test.local', 'password' => 'nope'])->assertStatus(401);
});

it('rejects login for a banned user', function () {
    apiUser(['banned' => true]);
    $this->postJson('/api/auth/login', ['email' => 'c@test.local', 'password' => 'secret1234'])->assertStatus(403);
});

// ---- /api/user ----

it('returns user info with a valid bearer token', function () {
    apiUser();
    $this->getJson('/api/user', ['Authorization' => 'Bearer TESTTOKEN123'])
        ->assertOk()->assertJsonPath('ret', 1)
        ->assertJsonPath('data.email', 'c@test.local')
        ->assertJsonPath('data.sub_token', 'SUBTOKEN32');
});

it('rejects /api/user without or with an invalid token', function () {
    apiUser();
    $this->getJson('/api/user')->assertStatus(401);
    $this->getJson('/api/user', ['Authorization' => 'Bearer WRONG'])->assertStatus(401);
});

it('rejects a banned user even with a valid token', function () {
    apiUser(['banned' => true]);
    $this->getJson('/api/user', ['Authorization' => 'Bearer TESTTOKEN123'])->assertStatus(403);
});

// ---- 设备上报(走同一 Bearer 中间件) ----

it('accepts device report via bearer token', function () {
    apiUser();
    $this->postJson('/api/device/report',
        ['device_id' => 'dev-1', 'platform' => 'android', 'model' => 'Pixel 8', 'app_version' => '1.0.0'],
        ['Authorization' => 'Bearer TESTTOKEN123'])
        ->assertOk()->assertJsonPath('ret', 1);
    $this->assertDatabaseHas('devices', ['device_id' => 'dev-1', 'platform' => 'android']);
});

it('rejects device report without a token', function () {
    $this->postJson('/api/device/report', ['device_id' => 'x'])->assertStatus(401);
});

// ---- 节点列表 ----

it('returns servable nodes filtered by class and online', function () {
    apiUser(['class' => 2]);
    App\Models\Node::create(['name' => 'HK', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 1, 'online' => true, 'secret' => 'a']);
    App\Models\Node::create(['name' => 'VIP', 'server' => 's', 'port' => 2, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 5, 'online' => true, 'secret' => 'b']);  // 等级不够
    App\Models\Node::create(['name' => 'OFF', 'server' => 's', 'port' => 3, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'online' => false, 'secret' => 'c']); // 离线
    $res = $this->getJson('/api/servers', ['Authorization' => 'Bearer TESTTOKEN123'])->assertOk();
    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.name'))->toBe('HK');
});

// ---- 公告 ----

it('returns only published announcements', function () {
    apiUser();
    App\Models\Announcement::create(['title' => '维护通知', 'content' => '今晚维护', 'published' => true, 'sort' => 1]);
    App\Models\Announcement::create(['title' => '草稿', 'content' => 'x', 'published' => false]);
    $res = $this->getJson('/api/announcements', ['Authorization' => 'Bearer TESTTOKEN123'])->assertOk();
    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.title'))->toBe('维护通知');
});

// ---- 签到 ----

it('checks in and rejects a second same-day checkin', function () {
    apiUser(['transfer_enable' => 1024 ** 3, 'last_check_in' => 0]);
    $this->postJson('/api/checkin', [], ['Authorization' => 'Bearer TESTTOKEN123'])
        ->assertOk()->assertJsonPath('ret', 1);
    $this->postJson('/api/checkin', [], ['Authorization' => 'Bearer TESTTOKEN123'])
        ->assertOk()->assertJsonPath('ret', 0);   // 当天再签被拒
});

// ---- 改密 ----

it('changes password with correct current password', function () {
    apiUser();
    $this->postJson('/api/account/password', ['current_password' => 'secret1234', 'password' => 'newpass1234'],
        ['Authorization' => 'Bearer TESTTOKEN123'])->assertOk()->assertJsonPath('ret', 1);
    $this->postJson('/api/auth/login', ['email' => 'c@test.local', 'password' => 'newpass1234'])->assertOk();
});

it('rejects password change with a wrong current password', function () {
    apiUser();
    $this->postJson('/api/account/password', ['current_password' => 'wrong', 'password' => 'newpass1234'],
        ['Authorization' => 'Bearer TESTTOKEN123'])->assertStatus(422);
});

// ---- 版本(公开) ----

it('returns app version info without a token', function () {
    $this->getJson('/api/app/version')->assertOk()->assertJsonPath('ret', 1)
        ->assertJsonStructure(['data' => ['latest', 'force', 'downloads' => ['android', 'ios', 'windows', 'macos']]]);
});
