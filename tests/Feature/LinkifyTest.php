<?php

it('turns urls into highlighted hyperlinks', function () {
    $html = linkify('访问 https://91vpn.com/help 查看');
    expect($html)->toContain('<a href="https://91vpn.com/help"');
    expect($html)->toContain('target="_blank"');
});

it('escapes html to prevent injection', function () {
    $html = linkify('<script>alert(1)</script>');
    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script&gt;');
});

it('keeps line breaks', function () {
    expect(linkify("第一行\n第二行"))->toContain('<br');
});
