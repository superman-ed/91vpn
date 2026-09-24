<?php

use App\Models\Plan;

/** 从落地页取出 JSON-LD 的 @graph，按 @type 索引。 */
function ld(): array
{
    $html = test()->get('/')->assertOk()->getContent();
    expect($html)->toContain('application/ld+json');
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    $out = [];
    foreach ($data['@graph'] as $node) {
        $out[$node['@type']] = $node;
    }

    return $out;
}

it('输出合法 JSON-LD，含 Organization / WebSite / FAQPage', function () {
    $g = ld();

    expect($g)->toHaveKeys(['Organization', 'WebSite', 'FAQPage']);
    expect($g['Organization']['name'])->toBe('91VPN');
    expect($g['WebSite']['inLanguage'])->toBe('zh-CN');
});

// `[!!]` 这一条是整组测试的核心 —— 结构化数据与可见内容不一致会失去展示资格。
it('FAQPage 的问答与页面上可见的折叠块逐字一致', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $g = ld();

    $questions = array_column($g['FAQPage']['mainEntity'], 'name');
    expect($questions)->not->toBeEmpty();

    foreach ($g['FAQPage']['mainEntity'] as $qa) {
        // 问题与答案都必须真的出现在 HTML 里(同源渲染,不是各写一份)
        expect($html)->toContain(e($qa['name']));
        expect($html)->toContain(e($qa['acceptedAnswer']['text']));
    }
});

// `[!!]` 这一条钉住一个【实测踩过的坑】:price 是带千位分隔符的展示字符串
//   ("1,800"),直接 (float) 转会得到 1.0 —— 当时最低价被算成 ¥1、
//   最高价从 1800 掉到 900,而页面显示完全正常,肉眼看不出来。
it('价格区间等于在售套餐的真实最低/最高价，不受千位分隔符影响', function () {
    // 造一个会触发千位分隔符的高价套餐
    Plan::create([
        'name' => '便宜舱', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100,
        'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1,
    ]);
    // `[!]` 1800 会被格式化成 "1,800" —— 正是这一档触发那个坑
    Plan::create([
        'name' => '头等舱', 'price' => 1800, 'period' => 'month', 'transfer_gb' => 12000,
        'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 1, 'on_sale' => true, 'stock' => -1,
    ]);

    $g = ld();
    expect($g)->toHaveKey('Product');
    $offers = $g['Product']['offers'];

    $real = Plan::where('on_sale', true)->pluck('price')->map(fn ($p) => (float) $p);

    expect((float) $offers['lowPrice'])->toBe($real->min());
    expect((float) $offers['highPrice'])->toBe($real->max())
        ->and((float) $offers['highPrice'])->toBeGreaterThanOrEqual(1800.0);
    expect($offers['priceCurrency'])->toBe('CNY');
});

// `[!]` 故意不输出 SoftwareApplication:四个平台的 client_downloads.url 全为空、
//   页面显示"即将推出",标一个下载不到的 App 是误导。下载链接填上后再加。
it('在没有可下载客户端时不标 SoftwareApplication', function () {
    expect(\App\Models\ClientDownload::visible()->whereNotNull('url')->count())
        ->toBe(0, '下载链接已经填上了 —— 该回来加 SoftwareApplication 了');

    expect(ld())->not->toHaveKey('SoftwareApplication');
});

it('favicon 用图标文件，不是 37 KB 的社交大图', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('favicon.svg');
    expect($html)->toMatch('#<link rel="icon"[^>]*favicon\.ico#');
    // og.jpg 只该出现在 og:image / apple-touch-icon,不该是 rel="icon"
    expect($html)->not->toMatch('#<link rel="icon"[^>]*og\.jpg#');
});
