<?php

use App\Models\User;

// 网站不开放注册,引导去客户端。注册逻辑(账户名/邀请归因等)由 API 与 RegistrationService 覆盖。
it('shows the register page directing users to the app', function () {
    $this->get('/register')->assertOk()->assertSee('请在客户端中注册');
});

it('does not create a user via the web register endpoint', function () {
    $this->post('/register', [
        'username' => 'webbie',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ])->assertRedirect('/register');

    expect(User::where('username', 'webbie')->exists())->toBeFalse();
});
