<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertSee('登录');
});

it('logs in with correct credentials and arithmetic captcha', function () {
    $user = User::factory()->create(['email' => 'lo@test.local', 'password' => Hash::make('secret1234')]);

    $this->withSession(['captcha_answer' => 5])->post('/login', [
        'email' => 'lo@test.local',
        'password' => 'secret1234',
        'captcha' => '5',
    ])->assertRedirect('/user');

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong password', function () {
    User::factory()->create(['email' => 'lo2@test.local', 'password' => Hash::make('secret1234')]);

    $this->withSession(['captcha_answer' => 5])->post('/login', [
        'email' => 'lo2@test.local',
        'password' => 'wrongpass',
        'captcha' => '5',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects wrong captcha', function () {
    User::factory()->create(['email' => 'lo3@test.local', 'password' => Hash::make('secret1234')]);

    $this->withSession(['captcha_answer' => 5])->post('/login', [
        'email' => 'lo3@test.local',
        'password' => 'secret1234',
        'captcha' => '99',
    ])->assertSessionHasErrors('captcha');

    $this->assertGuest();
});

it('logs out', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
