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

    $this->get('/')->assertOk()
        ->assertSee('VIP①')                             // 套餐名
        ->assertSee('data-price="19"', false)           // 首个时长价(切换按钮的数据)
        ->assertSee('1月', false)->assertSee('12月', false)  // 时长切换按钮
        ->assertSee('香港')                              // 节点名提取的地区
        ->assertSee('安卓版')                            // 真实下载
        ->assertDontSee('加油包');                       // 加油包不进套餐卡
});

it('shows fallbacks when no data is configured', function () {
    $this->get('/')->assertOk()
        ->assertSee('套餐即将上线')                       // 无在售套餐
        ->assertSee('香港');                             // 无节点 → 回落地区清单
});

it('redirects authenticated users from / to dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/user');
});

it('serves legal placeholder pages', function () {
    $this->get('/terms')->assertOk()->assertSee('服务条款');
    $this->get('/privacy')->assertOk()->assertSee('隐私政策');
    $this->get('/refund')->assertOk()->assertSee('退款政策');
});
