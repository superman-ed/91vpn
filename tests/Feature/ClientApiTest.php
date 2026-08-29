<?php

// apiUser() 辅助定义在 tests/Pest.php,供各 ClientApi 测试共用。

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

// ---- 注册(邮箱验证码,无 session 算术码) ----

it('registers with a valid email code and returns token + user', function () {
    Illuminate\Support\Facades\Cache::put('email_code:new@test.local', '123456', now()->addMinutes(5));
    $res = $this->postJson('/api/auth/register', [
        'email' => 'new@test.local', 'email_code' => '123456', 'name' => '小明', 'password' => 'secret1234',
    ])->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.token'))->not->toBeEmpty();
    $u = App\Models\User::where('email', 'new@test.local')->first();
    expect($u)->not->toBeNull();
    expect($u->class)->toBe(0);
    expect($u->api_token)->toBe($res->json('data.token'));   // 注册即自动登录
    expect($u->uuid)->not->toBeEmpty();
    expect($u->invite_token)->not->toBeEmpty();
});

it('rejects registration with a wrong email code', function () {
    Illuminate\Support\Facades\Cache::put('email_code:y@test.local', '123456', now()->addMinutes(5));
    $this->postJson('/api/auth/register', [
        'email' => 'y@test.local', 'email_code' => '000000', 'name' => 'y', 'password' => 'secret1234',
    ])->assertStatus(422);
    expect(App\Models\User::where('email', 'y@test.local')->exists())->toBeFalse();
});

it('rejects duplicate email only after the code passes', function () {
    App\Models\User::factory()->create(['email' => 'taken@test.local']);
    Illuminate\Support\Facades\Cache::put('email_code:taken@test.local', '123456', now()->addMinutes(5));
    $this->postJson('/api/auth/register', [
        'email' => 'taken@test.local', 'email_code' => '123456', 'name' => 'dup', 'password' => 'secret1234',
    ])->assertStatus(409)->assertJsonPath('ret', 0);
});

it('binds the inviter when a valid invite code is used at register', function () {
    $inviter = App\Models\User::factory()->create(['ref_code' => 'REFCODE99']);
    Illuminate\Support\Facades\Cache::put('email_code:z@test.local', '123456', now()->addMinutes(5));
    $this->postJson('/api/auth/register', [
        'email' => 'z@test.local', 'email_code' => '123456', 'name' => 'z',
        'invite_code' => 'REFCODE99', 'password' => 'secret1234',
    ])->assertOk();
    expect(App\Models\User::where('email', 'z@test.local')->first()->ref_by)->toBe($inviter->id);
});

// ---- 发码 / 找回 ----

it('sends a registration code and stores it in cache', function () {
    $this->postJson('/api/auth/send-code', ['email' => 'reg@test.local'])->assertOk()->assertJsonPath('ret', 1);
    expect(Illuminate\Support\Facades\Cache::get('email_code:reg@test.local'))->not->toBeNull();
});

it('sends a reset code only for a registered email but never reveals existence', function () {
    apiUser();   // c@test.local 存在
    $this->postJson('/api/auth/forgot', ['email' => 'c@test.local'])->assertOk()->assertJsonPath('ret', 1);
    expect(Illuminate\Support\Facades\Cache::get('email_code:c@test.local'))->not->toBeNull();
    // 未注册邮箱:同样回成功(不泄露),但不真发
    $this->postJson('/api/auth/forgot', ['email' => 'ghost@test.local'])->assertOk()->assertJsonPath('ret', 1);
    expect(Illuminate\Support\Facades\Cache::get('email_code:ghost@test.local'))->toBeNull();
});

it('resets password with a valid code and logs in with the new one', function () {
    apiUser();
    Illuminate\Support\Facades\Cache::put('email_code:c@test.local', '654321', now()->addMinutes(5));
    $this->postJson('/api/auth/reset', ['email' => 'c@test.local', 'code' => '654321', 'password' => 'brandnew99'])
        ->assertOk()->assertJsonPath('ret', 1);
    $this->postJson('/api/auth/login', ['email' => 'c@test.local', 'password' => 'brandnew99'])->assertOk();
});

it('rejects password reset with a wrong code', function () {
    apiUser();
    Illuminate\Support\Facades\Cache::put('email_code:c@test.local', '654321', now()->addMinutes(5));
    $this->postJson('/api/auth/reset', ['email' => 'c@test.local', 'code' => '000000', 'password' => 'brandnew99'])
        ->assertStatus(422);
});
