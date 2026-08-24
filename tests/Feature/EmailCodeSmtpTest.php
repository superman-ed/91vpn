<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

it('stores code and returns ok when SMTP not configured (log mode)', function () {
    Mail::fake();
    $this->post('/auth/send', ['email' => 'dev@test.local'])
        ->assertOk()->assertJson(['ok' => true]);

    expect(Cache::get('email_code:dev@test.local'))->not->toBeNull();
});

it('sends via SMTP path without error when configured', function () {
    Setting::put('smtp_host', 'smtp.example.com');
    Setting::put('smtp_username', 'noreply@example.com');
    Setting::put('smtp_password', 'secret');
    Mail::fake();

    $this->post('/auth/send', ['email' => 'user@test.local'])
        ->assertOk()->assertJson(['ok' => true]);

    expect(Cache::get('email_code:user@test.local'))->not->toBeNull();
    expect(smtp_configured())->toBeTrue();
});

it('admin saves SMTP settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->put('/admin/settings', [
        'smtp_host' => 'smtp.exmail.qq.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'smtp_username' => 'noreply@my.com', 'smtp_password' => 'authcode', 'smtp_from_name' => '91VPN',
    ])->assertRedirect('/admin/settings');

    expect(Setting::get('smtp_host'))->toBe('smtp.exmail.qq.com');
    expect(Setting::get('smtp_username'))->toBe('noreply@my.com');
});
