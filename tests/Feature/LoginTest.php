<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertSee('登录');
});

it('logs in with correct credentials and arithmetic captcha', function () {
    $user = User::factory()->create(['username' => 'louser', 'password' => Hash::make('secret1234')]);

    $this->withSession(['captcha_answer' => 5])->post('/login', [
        'username' => 'louser',
        'password' => 'secret1234',
        'captcha' => '5',
    ])->assertRedirect('/user');

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong password', function () {
    User::factory()->create(['username' => 'lo2user', 'password' => Hash::make('secret1234')]);

    $this->withSession(['captcha_answer' => 5])->post('/login', [
        'username' => 'lo2user',
        'password' => 'wrongpass',
        'captcha' => '5',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('rejects wrong captcha', function () {
    User::factory()->create(['username' => 'lo3user', 'password' => Hash::make('secret1234')]);

    $this->withSession(['captcha_answer' => 5])->post('/login', [
        'username' => 'lo3user',
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
