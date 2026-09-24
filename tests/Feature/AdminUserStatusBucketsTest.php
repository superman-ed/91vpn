<?php

use App\Models\User;

// `[!!]` 四个状态桶必须【完整划分】全体用户。2026-09-24 实测:
//   class > 0 而 class_expire 被清空的用户在【四个桶里都不出现】——
//   member 要 expire > now(NULL 比较为假)、expired 要 expire <= now(同样为假)。
//   而他功能上是用不了的(hasActivePackage false、订阅 403),会来投诉,
//   可你按"已过期"筛选看不到他。后台造出了一个后台自己找不到的状态。

function busAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'password' => 'adminpass123']);
}

function busUser(array $over): User
{
    return User::factory()->create(array_merge([
        'password' => 'x12345678', 'transfer_enable' => 10 * 1024 ** 3, 'u' => 0, 'd' => 0,
        'banned' => false,
    ], $over));
}

it('没有到期时间的有等级用户，算「已过期」', function () {
    $admin = busAdmin();
    $odd = busUser(['username' => 'nullexpire', 'class' => 1, 'class_expire' => null]);

    // 功能上他确实用不了 —— 桶要和这个事实一致
    expect($odd->hasActivePackage())->toBeFalse();

    $html = $this->actingAs($admin)->get('/admin/users?status=expired')->assertOk()->getContent();
    expect($html)->toContain('nullexpire');
});

it('他不会同时落进别的桶', function () {
    $admin = busAdmin();
    busUser(['username' => 'nullexpire', 'class' => 1, 'class_expire' => null]);

    foreach (['member', 'free', 'banned'] as $bucket) {
        $html = $this->actingAs($admin)->get("/admin/users?status={$bucket}")->assertOk()->getContent();
        // `[!]` 见 FinanceSearchAndTypesTest 的说明:toContain 不接受消息参数。
        expect(str_contains($html, 'nullexpire'))->toBeFalse("不该出现在 {$bucket} 桶里");
    }
});

// `[!!]` 这一条是真正的护栏:不管将来加什么状态,四个桶之和必须等于总数。
it('四个桶的计数之和等于用户总数', function () {
    $admin = busAdmin();   // 管理员也是用户,也要被算进去
    busUser(['username' => 'u_member', 'class' => 2, 'class_expire' => now()->addMonth()]);
    busUser(['username' => 'u_free', 'class' => 0, 'class_expire' => now()->subDay()]);
    busUser(['username' => 'u_expired', 'class' => 2, 'class_expire' => now()->subDay()]);
    busUser(['username' => 'u_nullexp', 'class' => 2, 'class_expire' => null]);
    busUser(['username' => 'u_banned', 'class' => 2, 'class_expire' => now()->addMonth(), 'banned' => true]);

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $counts = app('view')->shared('__env') ? null : null;   // 视图变量拿不到,改用控制器口径直接算

    $svc = new ReflectionMethod(\App\Http\Controllers\Admin\UserController::class, 'applyStatus');
    $svc->setAccessible(true);
    $c = new \App\Http\Controllers\Admin\UserController;

    $sum = 0;
    foreach (['member', 'free', 'expired', 'banned'] as $b) {
        $sum += $svc->invoke($c, User::query(), $b)->count();
    }

    expect($sum)->toBe(User::count(), '四个桶之和对不上总数 —— 有用户落在所有桶之外');
});
