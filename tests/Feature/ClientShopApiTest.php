<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

const AUTH = ['Authorization' => 'Bearer TESTTOKEN123'];

/** 造一个在售普通套餐(默认月付) */
function salePlan(array $attr = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'reset_type' => 'none',
        'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true,
    ], $attr));
}

// ---- 套餐目录 ----

it('lists on-sale plans grouped, with data packs separate', function () {
    apiUser();
    salePlan();
    Plan::create(['name' => '10GB 包', 'price' => 8, 'transfer_gb' => 10, 'is_data_pack' => true,
        'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 0, 'on_sale' => true]);
    Plan::create(['name' => '下架', 'price' => 99, 'period' => 'month', 'transfer_gb' => 1, 'class' => 9,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30, 'on_sale' => false]);   // 不出现

    $res = $this->getJson('/api/plans', AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.groups'))->toHaveCount(1);
    expect($res->json('data.groups.0.name'))->toBe('VIP①');
    expect($res->json('data.groups.0.durations.0.period'))->toBe('month');
    expect($res->json('data.data_packs'))->toHaveCount(1);
});

// ---- 下单 ----

it('creates a pending order and dedups the same plan', function () {
    apiUser();
    $plan = salePlan();
    $res = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->assertOk()->assertJsonPath('ret', 1);
    $id = $res->json('data.id');
    expect($res->json('data.status'))->toBe('pending');
    expect((float) $res->json('data.amount'))->toBe(30.0);
    // 再次下单同套餐 → 复用同一待支付订单
    $res2 = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->assertOk();
    expect($res2->json('data.id'))->toBe($id);
    expect(Order::where('user_id', User::first()->id)->count())->toBe(1);
});

it('rejects a data-pack order without an active package', function () {
    apiUser(['class' => 0, 'class_expire' => now()->subDay()]);
    $pack = Plan::create(['name' => '50GB 包', 'price' => 15, 'transfer_gb' => 50, 'is_data_pack' => true,
        'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 0, 'on_sale' => true]);
    $this->postJson('/api/order/create', ['plan_id' => $pack->id], AUTH)->assertStatus(422)->assertJsonPath('ret', 0);
});

// ---- 收银台信息 ----

it('shows checkout info including balance and online_pay', function () {
    apiUser(['money' => 50]);
    $plan = salePlan();
    $order = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->json('data');
    $res = $this->getJson("/api/order/{$order['id']}", AUTH)->assertOk();
    expect((float) $res->json('data.balance'))->toBe(50.0);
    expect($res->json('data.online_pay'))->toBeTrue();   // testing 环境允许模拟
    expect((float) $res->json('data.amount'))->toBe(30.0);
});

it('hides another user\'s order (404)', function () {
    apiUser();
    $other = User::factory()->create();
    $plan = salePlan();
    $order = Order::create(['user_id' => $other->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);
    $this->getJson("/api/order/{$order->id}", AUTH)->assertStatus(404);
});

// ---- 优惠码 ----

it('applies and removes a coupon, recomputing the amount', function () {
    apiUser();
    Coupon::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'max_use' => -1, 'enabled' => true]);
    $plan = salePlan();
    $order = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->json('data');

    $res = $this->postJson("/api/order/{$order['id']}/coupon", ['coupon' => 'SAVE20'], AUTH)->assertOk();
    expect((float) $res->json('data.amount'))->toBe(24.0);   // 30 * 0.8
    expect($res->json('data.coupon.code'))->toBe('SAVE20');
    // 留空移除 → 恢复原价
    $res2 = $this->postJson("/api/order/{$order['id']}/coupon", ['coupon' => ''], AUTH)->assertOk();
    expect((float) $res2->json('data.amount'))->toBe(30.0);
    expect($res2->json('data.coupon'))->toBeNull();
});

it('rejects an invalid coupon', function () {
    apiUser();
    $plan = salePlan();
    $order = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->json('data');
    $this->postJson("/api/order/{$order['id']}/coupon", ['coupon' => 'NOPE'], AUTH)->assertStatus(422);
});

// ---- 支付 ----

it('pays with balance and delivers, decrementing money', function () {
    apiUser(['money' => 100, 'class' => 0, 'class_expire' => now()->subDay()]);   // 无生效套餐→立即发货
    $plan = salePlan();
    $order = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->json('data');
    $this->postJson("/api/order/{$order['id']}/pay", ['method' => 'balance'], AUTH)
        ->assertOk()->assertJsonPath('ret', 1)->assertJsonPath('data.status', 'delivered');
    expect((float) User::first()->money)->toBe(70.0);   // 100 - 30
    expect(Order::find($order['id'])->status)->toBe('paid');
});

it('rejects balance payment when funds are insufficient', function () {
    apiUser(['money' => 5]);
    $plan = salePlan();
    $order = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->json('data');
    $this->postJson("/api/order/{$order['id']}/pay", ['method' => 'balance'], AUTH)
        ->assertStatus(402)->assertJsonPath('ret', 0);
    expect(Order::find($order['id'])->status)->toBe('pending');
});

it('mock-delivers an online payment in the testing env (no gateway)', function () {
    apiUser(['class' => 0, 'class_expire' => now()->subDay()]);   // 无生效套餐→立即发货
    $plan = salePlan();
    $order = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->json('data');
    $this->postJson("/api/order/{$order['id']}/pay", ['method' => 'alipay'], AUTH)
        ->assertOk()->assertJsonPath('ret', 1)->assertJsonPath('data.status', 'delivered');
    expect(Order::find($order['id'])->status)->toBe('paid');
});

// ---- 取消 ----

it('cancels a pending order', function () {
    apiUser();
    $plan = salePlan();
    $order = $this->postJson('/api/order/create', ['plan_id' => $plan->id], AUTH)->json('data');
    $this->postJson("/api/order/{$order['id']}/cancel", [], AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect(Order::find($order['id'])->status)->toBe('cancelled');
});
