<?php

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('records a login log on successful login', function () {
    $user = User::factory()->create(['email' => 'll@test.local', 'password' => Hash::make('secret1234')]);
    $this->withSession(['captcha_answer' => 5])->post('/login', [
        'email' => 'll@test.local', 'password' => 'secret1234', 'captcha' => '5',
    ])->assertRedirect('/user');

    $log = LoginLog::where('user_id', $user->id)->first();
    expect($log)->not->toBeNull();
    expect($log->ip)->not->toBeEmpty();
});

it('shows recent login logs on account page', function () {
    $user = User::factory()->create();
    LoginLog::create(['user_id' => $user->id, 'ip' => '1.2.3.4', 'location' => '测试', 'logged_at' => now()]);
    $this->actingAs($user)->get('/user/account')->assertOk()->assertSee('1.2.3.4');
});
