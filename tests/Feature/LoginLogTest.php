<?php

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function attempt(array $override = []): array
{
    return array_merge([
        'email' => 'u@test.local',
        'password' => 'secret1234',
        'captcha' => '7',
    ], $override);
}

it('records a successful login', function () {
    User::factory()->create(['email' => 'u@test.local', 'password' => Hash::make('secret1234'), 'banned' => false]);

    $this->withSession(['captcha_answer' => 7])->post('/login', attempt())->assertRedirect();

    $log = LoginLog::where('email', 'u@test.local')->first();
    expect($log->status)->toBe('success');
    expect($log->user_id)->not->toBeNull();
});

it('records a failed login with wrong password', function () {
    $u = User::factory()->create(['email' => 'u@test.local', 'password' => Hash::make('secret1234')]);

    $this->withSession(['captcha_answer' => 7])->post('/login', attempt(['password' => 'wrongpass']))
        ->assertSessionHasErrors('email');

    $log = LoginLog::where('status', 'failed')->first();
    expect($log)->not->toBeNull();
    expect($log->email)->toBe('u@test.local');
    expect($log->user_id)->toBe($u->id);        // 账号存在但密码错
    expect($log->reason)->toBe('邮箱或密码错误');
});

it('records a failed login for a non-existent account (user_id null)', function () {
    $this->withSession(['captcha_answer' => 7])->post('/login', attempt(['email' => 'ghost@test.local']))
        ->assertSessionHasErrors('email');

    $log = LoginLog::where('email', 'ghost@test.local')->first();
    expect($log->status)->toBe('failed');
    expect($log->user_id)->toBeNull();
});

it('surfaces a brute-force alert for an IP over the failure threshold', function () {
    // 造 6 条同 IP 失败(阈值 5)
    for ($i = 0; $i < 6; $i++) {
        LoginLog::create(['status' => 'failed', 'email' => "t{$i}@x.com", 'ip' => '9.9.9.9', 'logged_at' => now()->subMinutes($i)]);
    }
    // 另一 IP 只失败 2 次 → 不告警
    LoginLog::create(['status' => 'failed', 'email' => 'a@x.com', 'ip' => '1.1.1.1', 'logged_at' => now()]);
    LoginLog::create(['status' => 'failed', 'email' => 'b@x.com', 'ip' => '1.1.1.1', 'logged_at' => now()]);

    $res = $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/system/login-logs');
    $res->assertOk()->assertSee('暴破告警')->assertSee('9.9.9.9');

    $alerts = $res->viewData('alerts');
    expect($alerts)->toHaveCount(1);
    expect($alerts->first()->fails)->toBe(6);
});
