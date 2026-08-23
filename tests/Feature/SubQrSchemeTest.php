<?php

use App\Models\User;
use App\Support\QrCode;

it('qr code encodes the given scheme text', function () {
    // 二维码服务应把传入的 scheme 原样编码
    $uri = QrCode::dataUri('shadowrocket://add/sub://abc');
    expect($uri)->toStartWith('data:image/svg+xml;base64,');
    // SVG 本身不含明文，但生成不应报错且非空
    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    expect(strlen($svg))->toBeGreaterThan(100);
});

it('node page exposes scheme-based qr codes for each client', function () {
    $user = User::factory()->create(['invite_token' => 'QRSCHEME']);
    $res = $this->actingAs($user)->get('/user/node');
    $res->assertOk();
    // 页面应包含各客户端二维码图（data uri）与对应导入按钮
    $res->assertSee('小火箭');       // Shadowrocket 区
    $res->assertSee('Clash');
    $res->assertSee('shadowrocket://', false);
    $res->assertSee('clash://install-config', false);
});
