<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

it('shows the forgot password page', function () {
    $this->get('/password/forgot')->assertOk()->assertSee('找回密码');
});

it('sends a reset code for an existing email', function () {
    User::factory()->create(['email' => 'reset@test.local']);
    $this->postJson('/password/send', ['email' => 'reset@test.local'])->assertOk()->assertJson(['ok' => true]);
    expect(Cache::get('reset_code:reset@test.local'))->not->toBeNull();
});

it('resets the password with a valid code', function () {
    $user = User::factory()->create(['email' => 'reset2@test.local', 'password' => Hash::make('oldpass123')]);
    Cache::put('reset_code:reset2@test.local', '654321', now()->addMinutes(15));

    $this->post('/password/reset', [
        'email' => 'reset2@test.local',
        'code' => '654321',
        'password' => 'newpass1234',
        'password_confirmation' => 'newpass1234',
    ])->assertRedirect('/login');

    expect(Hash::check('newpass1234', $user->fresh()->password))->toBeTrue();
});

it('rejects reset with wrong code', function () {
    $user = User::factory()->create(['email' => 'reset3@test.local', 'password' => Hash::make('oldpass123')]);
    Cache::put('reset_code:reset3@test.local', '654321', now()->addMinutes(15));

    $this->post('/password/reset', [
        'email' => 'reset3@test.local',
        'code' => '000000',
        'password' => 'newpass1234',
        'password_confirmation' => 'newpass1234',
    ])->assertSessionHasErrors('code');

    expect(Hash::check('oldpass123', $user->fresh()->password))->toBeTrue();
});
