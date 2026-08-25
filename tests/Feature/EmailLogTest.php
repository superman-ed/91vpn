<?php

use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailCodeService;
use Illuminate\Support\Facades\Mail;

it('records a logged entry when SMTP is not configured', function () {
    app(EmailCodeService::class)->send('nobody@test.local');

    $log = EmailLog::where('to_email', 'nobody@test.local')->first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('logged');   // 未配 SMTP → 仅记录
    expect($log->type)->toBe('code');
});

it('records a sent entry when SMTP is configured and mail succeeds', function () {
    Setting::put('smtp_host', 'smtp.example.com');
    Setting::put('smtp_username', 'noreply@example.com');
    Setting::put('smtp_password', 'secret');
    Mail::fake();   // 拦截真实发信 → 视为成功

    app(EmailCodeService::class)->send('user@test.local');

    expect(EmailLog::where('to_email', 'user@test.local')->where('status', 'sent')->exists())->toBeTrue();
});

it('records a failed entry with error when mail throws', function () {
    Setting::put('smtp_host', 'smtp.example.com');
    Setting::put('smtp_username', 'noreply@example.com');
    Setting::put('smtp_password', 'secret');
    // 真实 SMTP 连接 smtp.example.com 会失败 → 记 failed
    app(EmailCodeService::class)->send('boom@test.local');

    $log = EmailLog::where('to_email', 'boom@test.local')->first();
    expect($log->status)->toBe('failed');
    expect($log->error)->not->toBeNull();
});

it('lists and filters email logs by status', function () {
    EmailLog::create(['to_email' => 'a@test.local', 'type' => 'code', 'subject' => 'X', 'status' => 'sent']);
    EmailLog::create(['to_email' => 'b@test.local', 'type' => 'code', 'subject' => 'X', 'status' => 'failed', 'error' => 'Connection timed out']);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin/system/emails')
        ->assertOk()->assertSee('a@test.local')->assertSee('b@test.local')
        ->assertViewHas('counts');

    $this->actingAs($admin)->get('/admin/system/emails?status=failed')
        ->assertOk()->assertSee('b@test.local')->assertDontSee('a@test.local')
        ->assertSee('Connection timed out');
});

it('peeks the current valid code from cache and records an audit', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    app(EmailCodeService::class); // ensure bound
    \Illuminate\Support\Facades\Cache::put('email_code:lost@test.local', '654321', now()->addMinutes(5));

    $res = $this->actingAs($admin)->get('/admin/system/emails?peek=lost@test.local');
    $res->assertOk()->assertSee('654321')->assertViewHas('peekCode', '654321');

    // 代查动作记入审计
    expect(\App\Models\AuditLog::where('action', 'email.peek_code')->exists())->toBeTrue();
});

it('shows no valid code when cache is empty or expired', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/admin/system/emails?peek=nobody@test.local')
        ->assertOk()->assertViewHas('peekCode', null)->assertSee('无有效验证码');
});
