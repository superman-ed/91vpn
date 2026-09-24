<?php

use App\Models\Setting;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 站点设置里有两项是【原样注入到公开页的脚本】:
//     tracking_code   注入 落地页/登录页/注册页/帮助/条款
//     support_widget  注入 登录页/注册页
//   [D] 2026-09-24 实测过注入范围,两者都覆盖【登录页】。
//
// `[!!]` 而它们原本只要 system.manage —— `infra`(运维) 角色就有。
//   能在登录页跑任意 JS = 能捕获任何登录者的口令,【包括超级管理员】。
//   也就是 infra 实际上等价于 super,而这违反 AdminAccess 自己写下的原则:
//   "能在界面上改权限的人,就能给自己加权限 —— 那么角色划分只剩装饰"。
// ─────────────────────────────────────────────────────────────────

function siAdmin(string $role): User
{
    return User::factory()->create(['is_admin' => true, 'admin_role' => $role, 'password' => 'a12345678']);
}

/** 站点设置表单要的最小字段集 */
function siPayload(array $over = []): array
{
    return array_merge([
        'buy_notice' => '', 'rebate_rate' => '2.5', 'signup_bonus' => '1',
        'free_traffic_cap_gb' => '2', 'smtp_port' => '465', 'smtp_encryption' => 'ssl',
        'smtp_from_name' => '91VPN',
        'tracking_code' => (string) setting('tracking_code', ''),
        'support_widget' => (string) setting('support_widget', ''),
    ], $over);
}

it('infra 角色改不了追踪代码', function () {
    Setting::put('tracking_code', '<script>/*OLD*/</script>');

    $this->actingAs(siAdmin('infra'))
        ->put('/admin/settings', siPayload(['tracking_code' => '<script>/*EVIL*/</script>']))
        ->assertSessionHasErrors('tracking_code');

    expect((string) setting('tracking_code'))->toBe('<script>/*OLD*/</script>', '值被改掉了');
});

it('infra 角色改不了客服挂件代码', function () {
    Setting::put('support_widget', '<script>/*OLD*/</script>');

    $this->actingAs(siAdmin('infra'))
        ->put('/admin/settings', siPayload(['support_widget' => '<script>/*EVIL*/</script>']))
        ->assertSessionHasErrors('support_widget');

    expect((string) setting('support_widget'))->toBe('<script>/*OLD*/</script>');
});

// `[!]` 非超管保存【其它】设置时,表单会把这两项的原值一起提交 —— 不该被拒。
it('infra 改别的设置不受影响', function () {
    Setting::put('tracking_code', '<script>/*KEEP*/</script>');

    $this->actingAs(siAdmin('infra'))
        ->put('/admin/settings', siPayload(['buy_notice' => '新的购买须知']))
        ->assertSessionHasNoErrors();

    expect((string) setting('buy_notice'))->toBe('新的购买须知');
    expect((string) setting('tracking_code'))->toBe('<script>/*KEEP*/</script>');
});

it('超级管理员照常能改', function () {
    $this->actingAs(siAdmin('super'))
        ->put('/admin/settings', siPayload([
            'tracking_code' => '<script>/*GA*/</script>',
            'support_widget' => '<script>/*CRISP*/</script>',
        ]))->assertSessionHasNoErrors();

    expect((string) setting('tracking_code'))->toContain('GA');
    expect((string) setting('support_widget'))->toContain('CRISP');
});

// `[!!]` 这一条钉住"为什么要管"——注入面里必须包含登录页。
//   哪天有人把 tracking 从 guest 布局里拿掉了,这条会提醒重新评估这个限制。
it('这两项确实会出现在登录页上（限制的理由）', function () {
    Setting::put('tracking_code', '<script>/*T_MARK*/</script>');
    Setting::put('support_widget', '<script>/*W_MARK*/</script>');

    $login = $this->get('/login')->assertOk()->getContent();

    expect(str_contains($login, 'T_MARK'))->toBeTrue();
    expect(str_contains($login, 'W_MARK'))->toBeTrue();
});
