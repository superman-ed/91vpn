<?php

// apiUser() 辅助定义在 tests/Pest.php,供各 ClientApi 测试共用。

// ---- 登录 ----

it('logs in with correct credentials and returns token + user info', function () {
    apiUser();
    $res = $this->postJson('/api/auth/login', ['username' => 'ctest', 'password' => 'secret1234'])->assertOk();
    $res->assertJsonPath('ret', 1)
        ->assertJsonPath('data.token', 'TESTTOKEN123')
        ->assertJsonPath('data.user.email', 'c@test.local')
        ->assertJsonPath('data.user.class', 1)
        ->assertJsonPath('data.user.sub_token', 'SUBTOKEN32');
    expect($res->json('data.user.transfer_remaining'))->toBe(7 * 1024 ** 3);   // 10 -(1+2)
});

it('rejects login with wrong password', function () {
    apiUser();
    $this->postJson('/api/auth/login', ['username' => 'ctest', 'password' => 'nope'])->assertStatus(401);
});

it('rejects login for a banned user', function () {
    apiUser(['banned' => true]);
    $this->postJson('/api/auth/login', ['username' => 'ctest', 'password' => 'secret1234'])->assertStatus(403);
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

it('lists all online nodes and flags the ones above the user class as locked', function () {
    apiUser(['class' => 2, 'class_expire' => now()->addDay()]);
    App\Models\Node::create(['name' => 'HK', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 1, 'online' => true, 'secret' => 'a']);
    App\Models\Node::create(['name' => 'VIP', 'server' => 's', 'port' => 2, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 5, 'online' => true, 'secret' => 'b']);  // 超出等级 → locked
    App\Models\Node::create(['name' => 'OFF', 'server' => 's', 'port' => 3, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'online' => false, 'secret' => 'c']); // 离线不列
    $res = $this->getJson('/api/servers', ['Authorization' => 'Bearer TESTTOKEN123'])->assertOk();

    $data = collect($res->json('data'));
    expect($data)->toHaveCount(2);   // 全量在线,含超等级的付费节点
    expect($data->firstWhere('name', 'HK')['locked'])->toBeFalse();
    expect($data->firstWhere('name', 'VIP')['locked'])->toBeTrue();
});

it('flags all paid nodes as locked for a non-member but still lists them', function () {
    apiUser(['class' => 0, 'class_expire' => now()->subDay()]);   // 非会员/过期
    App\Models\Node::create(['name' => 'FREE', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'online' => true, 'secret' => 'a']);
    App\Models\Node::create(['name' => 'PAID', 'server' => 's', 'port' => 2, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 1, 'online' => true, 'secret' => 'b']);
    $res = $this->getJson('/api/servers', ['Authorization' => 'Bearer TESTTOKEN123'])->assertOk();

    $data = collect($res->json('data'));
    expect($data)->toHaveCount(2);
    expect($data->firstWhere('name', 'FREE')['locked'])->toBeFalse();
    expect($data->firstWhere('name', 'PAID')['locked'])->toBeTrue();
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
    $this->postJson('/api/auth/login', ['username' => 'ctest', 'password' => 'newpass1234'])->assertOk();
});

it('rejects password change with a wrong current password', function () {
    apiUser();
    $this->postJson('/api/account/password', ['current_password' => 'wrong', 'password' => 'newpass1234'],
        ['Authorization' => 'Bearer TESTTOKEN123'])->assertStatus(422);
});

it('updates the nickname', function () {
    apiUser();
    $this->postJson('/api/account/profile', ['name' => '新昵称'], ['Authorization' => 'Bearer TESTTOKEN123'])
        ->assertOk()->assertJsonPath('ret', 1)->assertJsonPath('data.name', '新昵称');
    expect(App\Models\User::first()->name)->toBe('新昵称');
});

// ---- 版本(公开) ----

it('returns app version info without a token', function () {
    $this->getJson('/api/app/version')->assertOk()->assertJsonPath('ret', 1)
        ->assertJsonStructure(['data' => ['latest', 'force', 'downloads' => ['android', 'ios', 'windows', 'macos']]]);
});

// ---- 注册(账户名 + 密码,无邮箱验证码) ----

it('registers with username + password and returns token + user', function () {
    $res = $this->postJson('/api/auth/register', [
        'username' => 'newuser', 'name' => '小明', 'password' => 'secret1234',
    ])->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.token'))->not->toBeEmpty();
    $u = App\Models\User::where('username', 'newuser')->first();
    expect($u)->not->toBeNull();
    expect($u->class)->toBe(0);
    expect($u->api_token)->toBe($res->json('data.token'));   // 注册即自动登录
    expect($u->uuid)->not->toBeEmpty();
    expect($u->invite_token)->not->toBeEmpty();
});

it('defaults name to username when name omitted', function () {
    $this->postJson('/api/auth/register', ['username' => 'noname', 'password' => 'secret1234'])->assertOk();
    expect(App\Models\User::where('username', 'noname')->first()->name)->toBe('noname');
});

it('rejects a too-short or invalid username', function () {
    $this->postJson('/api/auth/register', ['username' => 'ab', 'password' => 'secret1234'])->assertStatus(422);
    $this->postJson('/api/auth/register', ['username' => 'bad name!', 'password' => 'secret1234'])->assertStatus(422);
    expect(App\Models\User::whereIn('username', ['ab', 'bad name!'])->exists())->toBeFalse();
});

it('rejects a duplicate username', function () {
    App\Models\User::factory()->create(['username' => 'taken']);
    $this->postJson('/api/auth/register', ['username' => 'taken', 'password' => 'secret1234'])
        ->assertStatus(409)->assertJsonPath('ret', 0);
});

it('binds the inviter when a valid invite code is used at register', function () {
    $inviter = App\Models\User::factory()->create(['ref_code' => 'REFCODE99']);
    $this->postJson('/api/auth/register', [
        'username' => 'zuser', 'name' => 'z', 'invite_code' => 'REFCODE99', 'password' => 'secret1234',
    ])->assertOk();
    expect(App\Models\User::where('username', 'zuser')->first()->ref_by)->toBe($inviter->id);
});
