<?php

use App\Models\LoginLog;
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

it('shows the empty placeholder on the device page when no install devices', function () {
    $this->actingAs(sysAdmin())->get('/admin/system/devices')
        ->assertOk()->assertSee('暂无已安装设备');
});
