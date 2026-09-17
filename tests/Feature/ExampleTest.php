<?php

use App\Models\User;

// `/` 现在是官网首页:游客看营销落地页(不再直接跳登录)。
it('serves the marketing landing page to guests at /', function () {
    $this->get('/')->assertOk()->assertSee('解锁')->assertSee('91VPN', false);
});

it('redirects authenticated users from / to dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/user');
});

// 条款占位页存在、不 404。
it('serves legal placeholder pages', function () {
    $this->get('/terms')->assertOk()->assertSee('服务条款');
    $this->get('/privacy')->assertOk()->assertSee('隐私政策');
    $this->get('/refund')->assertOk()->assertSee('退款政策');
});
