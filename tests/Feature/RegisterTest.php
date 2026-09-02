<?php

use App\Models\InviteCode;
use App\Models\User;

it('shows the register page', function () {
    $this->get('/register')->assertOk()->assertSee('注册');
});

it('registers a user with valid data and generates tokens', function () {
    $res = $this->withSession(['captcha_answer' => 7])->post('/register', [
        'username' => 'newuser',
        'name' => '小明',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ]);

    $res->assertRedirect('/user');
    $user = User::where('username', 'newuser')->first();
    expect($user)->not->toBeNull();
    expect($user->uuid)->not->toBeEmpty();
    expect($user->passwd)->not->toBeEmpty();
    expect($user->invite_token)->not->toBeEmpty();
    expect($user->api_token)->not->toBeEmpty();
    expect($user->class)->toBe(0);
});

it('defaults name to username when omitted', function () {
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'username' => 'noname',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ])->assertRedirect('/user');

    expect(User::where('username', 'noname')->first()->name)->toBe('noname');
});

it('rejects registration with wrong arithmetic captcha', function () {
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'username' => 'xuser',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '99',
    ])->assertSessionHasErrors('captcha');

    expect(User::where('username', 'xuser')->exists())->toBeFalse();
});

it('rejects a duplicate username', function () {
    User::factory()->create(['username' => 'taken']);

    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'username' => 'taken',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ])->assertSessionHasErrors('username');
});

it('binds inviter when a valid invite code is used', function () {
    $inviter = User::factory()->create();
    InviteCode::create(['code' => 'INVITE01', 'user_id' => $inviter->id]);

    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'username' => 'zuser',
        'name' => 'z',
        'invite_code' => 'INVITE01',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ])->assertRedirect('/user');

    $user = User::where('username', 'zuser')->first();
    expect($user->ref_by)->toBe($inviter->id);
    expect(InviteCode::where('code', 'INVITE01')->first()->used_by)->toBe($user->id);
});
