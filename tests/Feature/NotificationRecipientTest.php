<?php

use App\Models\User;
use App\Models\UserNotification;

// `[!!]` 站内信「单发」原本按【邮箱】找人,而本产品的注册不收邮箱
//   (AuthApiController::register 只要 username + password)。
//   [D] 2026-09-24 实测:输用户名连校验都过不去
//   ("The email field must be a valid email address"),0 条发出 ——
//   也就是这个功能对客户端注册的用户【完全不可用】。
//   生产 2 个用户里只有为 epay 对接建的那个有邮箱,owner 自己的没有。

function nrAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'password' => 'a12345678']);
}

it('能按用户名给没有邮箱的用户发站内信', function () {
    $u = User::factory()->create(['username' => 'summer', 'email' => null, 'password' => 'x12345678']);

    $this->actingAs(nrAdmin())->post('/admin/notifications', [
        'mode' => 'single', 'title' => '通知', 'content' => '正文', 'recipient' => 'summer',
    ])->assertSessionHasNoErrors();

    expect(UserNotification::where('user_id', $u->id)->count())->toBe(1);
});

it('按邮箱依然能发', function () {
    $u = User::factory()->create(['username' => 'withmail', 'email' => 'a@b.com', 'password' => 'x12345678']);

    $this->actingAs(nrAdmin())->post('/admin/notifications', [
        'mode' => 'single', 'title' => '通知', 'content' => '正文', 'recipient' => 'a@b.com',
    ])->assertSessionHasNoErrors();

    expect(UserNotification::where('user_id', $u->id)->count())->toBe(1);
});

// `[!!]` 错误键必须与表单里 @error 的键一致,否则"找不到用户"永远不显示 ——
//   查找逻辑修好了,而管理员看到的是一个没有任何反馈的空表单。
it('找不到用户时给出可见的错误，而不是静默', function () {
    $this->actingAs(nrAdmin())->post('/admin/notifications', [
        'mode' => 'single', 'title' => '通知', 'content' => '正文', 'recipient' => 'nobody',
    ])->assertSessionHasErrors('recipient');

    expect(UserNotification::count())->toBe(0);

    $view = file_get_contents(base_path('resources/views/admin/notifications/index.blade.php'));
    expect($view)->toContain("@error('recipient')");
    expect(str_contains($view, "@error('email')"))->toBeFalse('表单还在显示旧的错误键 —— 错误提示不会出现');
});

it('审计记的是 ident，不是空邮箱', function () {
    User::factory()->create(['username' => 'summer', 'email' => null, 'password' => 'x12345678']);

    $this->actingAs(nrAdmin())->post('/admin/notifications', [
        'mode' => 'single', 'title' => '通知', 'content' => '正文', 'recipient' => 'summer',
    ]);

    expect(\App\Models\AuditLog::latest('id')->first()->description)->toContain('summer');
});
