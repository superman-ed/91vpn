<?php

use App\Models\LoginLog;
use App\Models\SubscribeLog;
use App\Models\User;

function sysAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('renders login logs with user and client family', function () {
    $u = User::factory()->create(['email' => 'who@test.local']);
    LoginLog::create(['user_id' => $u->id, 'ip' => '1.2.3.4', 'user_agent' => 'ClashforWindows/0.20', 'logged_at' => now()]);

    $this->actingAs(sysAdmin())->get('/admin/system/login-logs')
        ->assertOk()
        ->assertSee('who@test.local')
        ->assertSee('1.2.3.4')
        ->assertSee('Clash for Windows')
        ->assertViewHas('counts');
});

it('filters login logs by ip', function () {
    $u = User::factory()->create();
    LoginLog::create(['user_id' => $u->id, 'ip' => '9.9.9.9', 'logged_at' => now()]);
    LoginLog::create(['user_id' => $u->id, 'ip' => '8.8.8.8', 'logged_at' => now()]);

    $this->actingAs(sysAdmin())->get('/admin/system/login-logs?q=9.9.9.9')
        ->assertOk()->assertSee('9.9.9.9')->assertDontSee('8.8.8.8');
});

it('aggregates client families on device page (latest per user)', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    // a 先用 v2rayN，后换 Shadowrocket → 应只算最近的 Shadowrocket
    SubscribeLog::create(['user_id' => $a->id, 'client' => 'v2rayN/6.0', 'fetched_at' => now()->subHour()]);
    SubscribeLog::create(['user_id' => $a->id, 'client' => 'Shadowrocket/1.9', 'fetched_at' => now()]);
    SubscribeLog::create(['user_id' => $b->id, 'client' => 'Shadowrocket/2.0', 'fetched_at' => now()]);

    $res = $this->actingAs(sysAdmin())->get('/admin/system/devices');
    $res->assertOk()
        ->assertViewHas('totalUsers', 2)
        ->assertViewHas('totalFetches', 3)
        ->assertSee('小火箭 Shadowrocket');

    $fam = $res->viewData('byFamily');
    expect($fam->get('小火箭 Shadowrocket'))->toBe(2);   // a(最近) + b
    expect($fam->has('v2rayN (Windows)'))->toBeFalse();  // a 的旧记录不计
});

it('classifies device platforms from client UA', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();
    SubscribeLog::create(['user_id' => $a->id, 'client' => 'ClashforWindows/0.20', 'fetched_at' => now()]);
    SubscribeLog::create(['user_id' => $b->id, 'client' => 'v2rayNG/1.8', 'fetched_at' => now()]);
    SubscribeLog::create(['user_id' => $c->id, 'client' => 'Shadowrocket/2.2', 'fetched_at' => now()]);

    $res = $this->actingAs(sysAdmin())->get('/admin/system/devices');
    $res->assertOk()->assertSee('设备 / 平台分布');

    $plat = $res->viewData('byPlatform');
    expect($plat->get('Windows'))->toBe(1);
    expect($plat->get('Android'))->toBe(1);
    expect($plat->get('iOS'))->toBe(1);
});
