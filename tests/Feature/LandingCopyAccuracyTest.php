<?php

use App\Models\ClientDownload;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 落地页是要花钱投广告的页面,上面的每句承诺都会被访客当真、
//   也会被广告平台核查。2026-09-24 实测发现三处与事实不符:
//     ① 三步写成「STEP 01 注册 → STEP 02 下载」,而网站【不受理注册】
//        (RegisterController::store 直接挡回提示页)。按原顺序走的人会到
//        /register 看见"请回首页下载客户端",转一圈回到原点。
//     ② 写「邮箱注册」,而注册只收 username/password,没有邮箱字段。
//     ③ 写「提供 Android、Windows、iOS、macOS 客户端」,而四个平台的
//        client_downloads.url 全为空、页面显示"即将推出"。
//   这组测试钉住这三处。
// ─────────────────────────────────────────────────────────────────

it('三步的顺序是先下载、后在客户端里注册', function () {
    $html = $this->get('/')->assertOk()->getContent();

    $p1 = mb_strpos($html, '下载客户端');
    $p2 = mb_strpos($html, '注册账号');
    expect($p1)->not->toBeFalse()->and($p2)->not->toBeFalse();
    expect($p1)->toBeLessThan($p2, '注册排在下载前面 —— 网站不受理注册,照这个顺序走会走进死路');
});

it('不说「邮箱注册」—— 注册没有邮箱字段', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('邮箱注册');
    // 正面说明也要在:说清只要账户名和密码
    expect($html)->toContain('无需邮箱');
});

// `[!!]` 这两条成对:平台清单【从 $downloads 生成】,不写死。
//   写死过一次,于是页面显示"即将推出"而文案说"提供四个平台客户端"。
it('没有可下载客户端时，说的是「即将开放下载」', function () {
    ClientDownload::query()->update(['url' => null]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('即将开放下载');
    expect($html)->not->toContain('现提供');
});

it('填上下载链接后，文案自己改口说「现提供」', function () {
    ClientDownload::query()->update(['url' => null]);
    $win = ClientDownload::where('platform', 'Windows')->first();
    expect($win)->not->toBeNull();
    $win->update(['url' => 'https://example.com/91vpn-win.exe']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('现提供');
    expect($html)->toContain('Windows');
    // 其余平台仍该被说成即将开放
    expect($html)->toContain('即将开放下载');
});

// `[!]` FAQ 与结构化数据同源,所以动态改口时 JSON-LD 必须跟着变。
it('平台清单改口时，FAQPage 结构化数据跟着变', function () {
    ClientDownload::query()->update(['url' => null]);
    ClientDownload::where('platform', 'Android')->update(['url' => 'https://example.com/a.apk']);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $graph = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
    $faq = collect($graph)->firstWhere('@type', 'FAQPage');

    $answers = implode(' ', array_column(array_column($faq['mainEntity'], 'acceptedAnswer'), 'text'));
    expect($answers)->toContain('现提供')->toContain('Android');
});
