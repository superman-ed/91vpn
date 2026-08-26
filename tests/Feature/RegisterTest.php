<?php

use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('shows the register page', function () {
    $this->get('/register')->assertOk()->assertSee('注册');
});

it('registers a user with valid data and generates tokens', function () {
    Cache::put('email_code:new@test.local', '123456', now()->addMinutes(5));

    $res = $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'new@test.local',
        'email_code' => '123456',
        'name' => '小明',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ]);

    $res->assertRedirect('/user');
    $user = User::where('email', 'new@test.local')->first();
    expect($user)->not->toBeNull();
    expect($user->uuid)->not->toBeEmpty();
    expect($user->passwd)->not->toBeEmpty();
    expect($user->invite_token)->not->toBeEmpty();
    expect($user->api_token)->not->toBeEmpty();
    expect($user->class)->toBe(0);
});

it('rejects registration with wrong arithmetic captcha', function () {
    Cache::put('email_code:x@test.local', '123456', now()->addMinutes(5));

    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'x@test.local',
        'email_code' => '123456',
        'name' => 'x',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '99',
    ])->assertSessionHasErrors('captcha');

    expect(User::where('email', 'x@test.local')->exists())->toBeFalse();
});

it('rejects registration with wrong email code', function () {
    Cache::put('email_code:y@test.local', '123456', now()->addMinutes(5));

    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'y@test.local',
        'email_code' => '000000',
        'name' => 'y',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ])->assertSessionHasErrors('email_code');

    expect(User::where('email', 'y@test.local')->exists())->toBeFalse();
});

it('does not leak email existence before code is verified (anti-enumeration)', function () {
    User::factory()->create(['email' => 'taken@test.local']);
    // 无有效验证码时探测已注册邮箱:只应回验证码错误,不得暴露 email 已注册
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'taken@test.local',
        'email_code' => '000000',
        'name' => 'probe',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ])->assertSessionHasErrors('email_code')->assertSessionDoesntHaveErrors('email');
});

it('rejects duplicate email only after code passes (owner sees clear hint)', function () {
    User::factory()->create(['email' => 'taken2@test.local']);
    Cache::put('email_code:taken2@test.local', '123456', now()->addMinutes(5));
    // 掌握该邮箱(收到验证码)后,才提示已注册
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'taken2@test.local',
        'email_code' => '123456',
        'name' => 'dup',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ])->assertSessionHasErrors('email');
});

it('binds inviter when a valid invite code is used', function () {
    $inviter = User::factory()->create();
    InviteCode::create(['code' => 'INVITE01', 'user_id' => $inviter->id]);
    Cache::put('email_code:z@test.local', '123456', now()->addMinutes(5));

    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'z@test.local',
        'email_code' => '123456',
        'name' => 'z',
        'invite_code' => 'INVITE01',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ])->assertRedirect('/user');

    $user = User::where('email', 'z@test.local')->first();
    expect($user->ref_by)->toBe($inviter->id);
    expect(InviteCode::where('code', 'INVITE01')->first()->used_by)->toBe($user->id);
});
