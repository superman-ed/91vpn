<?php

// 非官网域(≠ APP_URL host)下发 noindex,官网域不下发。用 /robots.txt 作轻量探针(web 组,无重依赖)。

it('adds noindex on a non-canonical host (app./sub.)', function () {
    config(['app.url' => 'https://91vpn.com']);

    $this->get('http://app.91app.shop/robots.txt')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get('http://sub.91app.shop/robots.txt')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('does not add noindex on the canonical host (91vpn.com)', function () {
    config(['app.url' => 'https://91vpn.com']);

    $res = $this->get('http://91vpn.com/robots.txt')->assertOk();
    expect($res->headers->has('X-Robots-Tag'))->toBeFalse();
});
