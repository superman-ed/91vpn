<?php

use App\Models\User;

it('requires auth to view dashboard', function () {
    $this->get('/user')->assertRedirect('/login');
});

it('shows traffic, expire, class, balance on dashboard', function () {
    $user = User::factory()->create([
        'class' => 2,
        'class_expire' => now()->addDays(20),
        'transfer_enable' => 300 * 1024 ** 3,   // 300 GB
        'u' => 50 * 1024 ** 3,                    // 已用 50 GB 上传
        'd' => 30 * 1024 ** 3,                    // 已用 30 GB 下载
        'money' => 12.50,
    ]);

    $res = $this->actingAs($user)->get('/user');
    $res->assertOk();
    $res->assertSee('剩余 20 天');                        // 会员时长
    $res->assertSee('VIP②');                             // 等级名
    $res->assertSee(now()->addDays(20)->format('Y-m-d')); // 到期日期
    $res->assertSee('220');            // 剩余 220 GB (300-80)
    $res->assertSee('12.50');          // 余额
});

it('formats bytes to GB via helper', function () {
    expect(bytes_to_gb(300 * 1024 ** 3))->toBe(300.0);
    expect(bytes_to_gb(0))->toBe(0.0);
});
