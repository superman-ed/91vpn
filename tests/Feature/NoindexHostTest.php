<?php

// 非官网域(≠ APP_URL host)下发 noindex。网页已由 WebOnOfficialHost 跳回官网,所以 noindex
// 现在主要护住放行路径 /sub(订阅含 token,不该被索引)—— 用它作探针(不被重定向)。

it('adds noindex on a non-canonical host for passthrough paths (/sub)', function () {
    config(['app.url' => 'https://91vpn.com']);

    $this->get('http://sub.91app.shop/sub/__probe__')
        ->assertNotFound()   // 未知 token → SubController 404,且未被重定向(放行)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('does not add noindex on the canonical host (91vpn.com)', function () {
    config(['app.url' => 'https://91vpn.com']);

    $res = $this->get('http://91vpn.com/robots.txt')->assertOk();
    expect($res->headers->has('X-Robots-Tag'))->toBeFalse();
});
