<?php

// ─────────────────────────────────────────────────────────────────
// `[!!]` 2026-09-24 实测:四个游客模板都在 <link rel="stylesheet"> 里请求
//   Noto Sans SC,而那份【阻塞渲染】的 CSS 有 345,997 字节 —— 是落地页
//   HTML(38 KB)的 9 倍,里面 324 个 @font-face 有 303 个是中文切片。
//
//   改成"中文交给系统字体"后降到 9,022 字节(2%)。理由:中文用户的系统字体
//   本来就好 —— Windows 微软雅黑、macOS/iOS 苹方、安卓 Noto Sans CJK
//   (跟 Noto Sans SC 基本同一套)。legal.blade.php 早就是这么做的。
//
// `[!]` 当时不能只从字体栈里删掉 "Noto Sans SC" —— --body 原本是
//   `"Noto Sans SC","Archivo",sans-serif`,删掉后 400 字重的英文正文会落到
//   只加载了 700/800/900 的 Archivo 上,被浏览器拿 700 凑,发粗。
//   所以 --body 整栈换成了系统字体。
// ─────────────────────────────────────────────────────────────────

/** 游客能直接打开、且自带 <head> 的页面。 */
function guestPages(): array
{
    return ['/', '/help', '/login', '/register'];
}

it('游客页面不请求中文网络字体', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();

    $urls = [];
    preg_match_all('#https://fonts\.googleapis\.com/css2\?[^"\']+#', $html, $urls);

    foreach ($urls[0] ?? [] as $u) {
        expect($u)->not->toContain('Noto+Sans+SC',
            "{$path} 又在请求中文网络字体 —— 那份阻塞渲染的 CSS 会从 9 KB 涨回 346 KB。");
        expect($u)->not->toContain('Source+Han');
    }
})->with(guestPages());

it('中文仍然有明确的系统字体可用，而不是只靠 sans-serif 兜底', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();

    // 至少要点名一个真实存在的中文系统字体,否则各平台回落结果不可控
    expect($html)->toContain('PingFang SC');       // macOS / iOS
    expect($html)->toContain('Microsoft YaHei');   // Windows
})->with(guestPages());

// `[!]` 这一条是防"把 --body 也交给 Archivo"那个坑回来:Archivo 没有 400 字重。
it('正文字体栈里不含只加载了粗字重的 Archivo', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/--body:[^;}]*/');
    preg_match('/--body:([^;}]*)/', $html, $m);
    expect($m[1])->not->toContain('Archivo');
});
