<?php

use App\Models\Setting;
use App\Models\User;

// 账户体系已去邮箱:发码/邮箱验证码路由移除。SMTP 设置本身保留(后台可配),此处只测保存。
it('admin saves SMTP settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->put('/admin/settings', [
        'smtp_host' => 'smtp.exmail.qq.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'smtp_username' => 'noreply@my.com', 'smtp_password' => 'authcode', 'smtp_from_name' => '91VPN',
    ])->assertRedirect('/admin/settings');

    expect(Setting::get('smtp_host'))->toBe('smtp.exmail.qq.com');
    expect(Setting::get('smtp_username'))->toBe('noreply@my.com');
});
