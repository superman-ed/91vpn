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

it('still shows ticket entry when no instant channels are set', function () {
    $res = $this->actingAs(User::factory()->create())->get('/user');
    $res->assertOk()
        ->assertSee('cs-bubble', false)
        ->assertSee('提交工单')
        ->assertDontSee('Telegram 客服');
});
