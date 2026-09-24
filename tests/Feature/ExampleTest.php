<?php

use App\Models\ClientDownload;
use App\Models\Node;
use App\Models\Plan;
use App\Models\User;

// `/` 是官网首页:游客看营销落地页,价格/地区/下载读真实数据。

it('renders real plans (grouped, with duration toggle), regions and downloads', function () {
    // 同名套餐两个时长 → 归成一组、组内两个时长按钮(与商店同结构)
    Plan::create(['name' => 'VIP①', 'price' => 19, 'period' => 'month', 'transfer_gb' => 100,
        'reset_type' => 'monthly', 'is_data_pack' => false, 'class' => 0, 'ip_limit' => 3,
        'duration_days' => 30, 'sort' => 1, 'on_sale' => true]);
    Plan::create(['name' => 'VIP①', 'price' => 200, 'period' => 'year', 'transfer_gb' => 100,
        'reset_type' => 'monthly', 'is_data_pack' => false, 'class' => 0, 'ip_limit' => 3,
        'duration_days' => 365, 'sort' => 1, 'on_sale' => true]);
    Plan::create(['name' => '加油包', 'price' => 5, 'period' => 'none', 'transfer_gb' => 20,
        'reset_type' => 'none', 'is_data_pack' => true, 'class' => 0, 'on_sale' => true]);
    Node::create(['name' => '香港 01', 'server' => '1.1.1.1', 'port' => 443, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's', 'role' => 'landing', 'enabled' => true]);
    ClientDownload::create(['platform' => 'Android', 'label' => '安卓版', 'url' => 'https://x.example/app.apk', 'enabled' => true, 'sort' => 1]);

    $this->get(config('app.url').'/')->assertOk()       // 落地页只在官网域(APP_URL host)出
        ->assertSee('VIP①')                             // 套餐名
        ->assertSee('data-price="19"', false)           // 首个时长价(切换按钮的数据)
        ->assertSee('1月', false)->assertSee('12月', false)  // 时长切换按钮
        ->assertSee('香港')                              // 节点名提取的地区
        ->assertSee('安卓版')                            // 真实下载
        ->assertDontSee('加油包');                       // 加油包不进套餐卡
});

it('shows fallbacks when no data is configured', function () {
    $this->get(config('app.url').'/')->assertOk()
        ->assertSee('套餐即将上线')                       // 无在售套餐
        ->assertSee('香港');                             // 无节点 → 回落地区清单
});

it('redirects authenticated users from / to dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/user');
});

it('serves web pages only on the official host; non-official hosts redirect there', function () {
    config(['app.url' => 'https://91vpn.com']);
    // 非官网域的网页 → 302 跳官网同路径(app.91app.shop 因此只剩 /api 当纯 API 域)
    $this->get('http://app.91app.shop/')->assertRedirect('https://91vpn.com/');
    $this->get('http://app.91app.shop/login')->assertRedirect('https://91vpn.com/login');
    // [!!] 放行不能跳:/sub 必须原样服务(误跳会把全员订阅搬走)→ 命中 SubController 得 404,而非 302
    $this->get('http://app.91app.shop/sub/__nope__')->assertNotFound();
});

it('serves legal placeholder pages', function () {
    $this->get('/terms')->assertOk()->assertSee('服务条款');
    $this->get('/privacy')->assertOk()->assertSee('隐私政策');
    $this->get('/refund')->assertOk()->assertSee('退款政策');
});

// 公开帮助中心
it('serves the public help center', function () {
    \App\Models\HelpArticle::create(['category' => '安装', 'platform' => 'windows', 'title' => 'Windows 安装教程', 'content' => "第一步\n第二步", 'published' => true, 'sort' => 1]);
    \App\Models\HelpArticle::create(['category' => '安装', 'platform' => 'all', 'title' => '草稿未发布', 'content' => 'x', 'published' => false]);

    $this->get('/help')->assertOk()->assertSee('Windows 安装教程')->assertDontSee('草稿未发布');
    $id = \App\Models\HelpArticle::where('published', true)->first()->id;
    $this->get('/help/'.$id)->assertOk()->assertSee('第一步');
});

it('hides unpublished help articles (404)', function () {
    $a = \App\Models\HelpArticle::create(['category' => 'x', 'platform' => 'all', 'title' => '隐藏', 'content' => 'x', 'published' => false]);
    $this->get('/help/'.$a->id)->assertNotFound();
});

// SEO:robots / sitemap 动态
it('serves robots.txt and sitemap.xml', function () {
    \App\Models\HelpArticle::create(['category' => 'x', 'platform' => 'all', 'title' => 't', 'content' => 'c', 'published' => true]);
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:')->assertSee('Disallow: /admin');
    $r = $this->get('/sitemap.xml')->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('xml');
    $r->assertSee('/help', false);
});

// 面板可设置:OG 图 / 条款正文
it('landing og image and legal content come from settings', function () {
    \App\Models\Setting::put('og_image', 'https://cdn.example/og-real.png');
    \App\Models\Setting::put('terms_content', "第一条 测试条款\n第二条 xyz");

    $this->get(config('app.url').'/')->assertOk()->assertSee('https://cdn.example/og-real.png', false);
    $this->get('/terms')->assertOk()->assertSee('第一条 测试条款')->assertDontSee('本页内容整理中');
    $this->get('/refund')->assertOk()->assertSee('本页内容整理中');   // 未设 → 兜底
});

it('admin can save og_image and refund_content', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->from('/admin/settings')->put('/admin/settings', [
        'og_image' => 'https://cdn.example/x.png', 'refund_content' => '七天退款',
    ])->assertRedirect('/admin/settings');
    expect(setting('og_image'))->toBe('https://cdn.example/x.png');
    expect(setting('refund_content'))->toBe('七天退款');
});
