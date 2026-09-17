<?php

use App\Models\ClientDownload;
use App\Models\Node;
use App\Models\Plan;
use App\Models\User;

// `/` 是官网首页:游客看营销落地页,价格/地区/下载读真实数据。

it('renders real plans, regions and downloads on the landing', function () {
    Plan::create([
        'name' => '标准月付', 'price' => 19, 'period' => 'month', 'transfer_gb' => 100,
        'reset_type' => 'monthly', 'is_data_pack' => false, 'class' => 0,
        'ip_limit' => 3, 'sort' => 1, 'on_sale' => true,
    ]);
    // 加油包不该出现在首页价格表
    Plan::create(['name' => '加油包', 'price' => 5, 'period' => 'none', 'transfer_gb' => 20,
        'reset_type' => 'none', 'is_data_pack' => true, 'class' => 0, 'on_sale' => true]);
    Node::create(['name' => '香港 01', 'server' => '1.1.1.1', 'port' => 443, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's', 'role' => 'landing', 'enabled' => true]);
    ClientDownload::create(['platform' => 'Android', 'label' => '安卓版', 'url' => 'https://x.example/app.apk', 'enabled' => true, 'sort' => 1]);

    $this->get('/')->assertOk()
        ->assertSee('标准月付')->assertSee('¥19')      // 真实套餐名+价格
        ->assertSee('100 GB')
        ->assertSee('香港')                              // 从节点名提取的地区
        ->assertSee('安卓版')                            // 真实下载
        ->assertDontSee('加油包');                       // 加油包不进价格表
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
