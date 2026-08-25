<?php

use App\Models\Setting;
use App\Models\User;

it('shows the self-built support bubble with configured links by default', function () {
    Setting::put('support_tg', 'https://t.me/mysupport');
    Setting::put('support_group', 'https://t.me/mygroup');
    Setting::put('support_hours', '每日 10:00-24:00');

    $res = $this->actingAs(User::factory()->create())->get('/user');
    $res->assertOk()
        ->assertSee('cs-bubble', false)
        ->assertSee('https://t.me/mysupport', false)
        ->assertSee('https://t.me/mygroup', false)
        ->assertSee('每日 10:00-24:00')
        ->assertSee('/user/tickets', false);   // 工单入口恒在
});

it('injects the third-party widget and hides the self-built panel when configured', function () {
    Setting::put('support_widget', '<script>window.__CS_LOADED=1;</script>');

    $res = $this->actingAs(User::factory()->create())->get('/user');
    $res->assertOk()
        ->assertSee('window.__CS_LOADED=1', false)
        ->assertDontSee('id="cs-bubble"', false);   // 自建气泡隐藏
});

it('loads Crisp but stays anonymous by default (identity binding off)', function () {
    Setting::put('crisp_website_id', '233710e4-9a5f-4b81-be1e-a1cb6fe17a62');
    $user = User::factory()->create(['email' => 'vip@test.local', 'name' => '小明']);

    $res = $this->actingAs($user)->get('/user');
    $res->assertOk()
        ->assertSee('client.crisp.chat/l.js', false)
        ->assertDontSee('vip@test.local', false)       // 默认不透传身份 → 匿名，省档案额度
        ->assertDontSee('user:email', false)
        ->assertDontSee('id="cs-bubble"', false);
});

it('binds the logged-in user identity when the switch is on', function () {
    Setting::put('crisp_website_id', '233710e4-9a5f-4b81-be1e-a1cb6fe17a62');
    Setting::put('crisp_bind_identity', '1');
    $user = User::factory()->create(['email' => 'vip@test.local', 'name' => '小明']);

    $res = $this->actingAs($user)->get('/user');
    $res->assertOk()
        ->assertSee('client.crisp.chat/l.js', false)
        ->assertSee('vip@test.local', false)           // 开关开 → 身份透传
        ->assertSee('user:nickname', false);
});

it('persists the identity switch from the settings form', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->put('/admin/settings', [
        'crisp_website_id' => '233710e4-9a5f-4b81-be1e-a1cb6fe17a62',
        'crisp_bind_identity' => '1',
    ]);
    expect(setting('crisp_bind_identity', '0'))->toBe('1');

    // 不勾选 → 关
    $this->actingAs($admin)->put('/admin/settings', [
        'crisp_website_id' => '233710e4-9a5f-4b81-be1e-a1cb6fe17a62',
    ]);
    expect(setting('crisp_bind_identity', '0'))->toBe('0');
});

it('prefers Crisp over the generic third-party widget', function () {
    Setting::put('crisp_website_id', '233710e4-9a5f-4b81-be1e-a1cb6fe17a62');
    Setting::put('support_widget', '<script>window.__OTHER=1;</script>');

    $res = $this->actingAs(User::factory()->create())->get('/user');
    $res->assertOk()
        ->assertSee('client.crisp.chat', false)
        ->assertDontSee('window.__OTHER=1', false);    // 通用代码被忽略
});

it('rejects a malformed Crisp website id', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->from('/admin/settings')
        ->put('/admin/settings', ['crisp_website_id' => 'not-a-valid-id'])
        ->assertSessionHasErrors('crisp_website_id');
});

it('still shows ticket entry when no instant channels are set', function () {
    $res = $this->actingAs(User::factory()->create())->get('/user');
    $res->assertOk()
        ->assertSee('cs-bubble', false)
        ->assertSee('提交工单')
        ->assertDontSee('Telegram 客服');
});
