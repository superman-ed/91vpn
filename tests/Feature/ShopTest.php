<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

it('lists on-sale plans in shop', function () {
    Plan::create(['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    Plan::create(['name' => '下架套餐', 'price' => 99, 'transfer_gb' => 999, 'class' => 9, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30, 'on_sale' => false]);

    $this->actingAs(User::factory()->create())->get('/user/shop')
        ->assertOk()->assertSee('VIP①')->assertDontSee('下架套餐');
});

it('creates a pending order', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);

    $this->actingAs($user)->post('/user/order/create', ['plan_id' => $plan->id])->assertRedirect();

    $order = Order::where('user_id', $user->id)->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('pending');
    expect((float) $order->amount)->toBe(30.0);

    // 下单后落到收银台结算页
    $this->actingAs($user)->get("/user/order/{$order->id}")
        ->assertOk()->assertSee('订单结算')->assertSee('应付金额')->assertSee('VIP①');
});

it('redirects checkout to wallet when order already paid', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month']);

    $this->actingAs($user)->get("/user/order/{$order->id}")->assertRedirect('/user/wallet');
});

it('cannot open another user checkout', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);
    $order = Order::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($other)->get("/user/order/{$order->id}")->assertForbidden();
});

it('mock-pays an order and delivers the plan', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay(), 'transfer_enable' => 0]);
    $plan = Plan::create(['name' => 'VIP②', 'price' => 50, 'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200, 'ip_limit' => 7, 'duration_days' => 30, 'on_sale' => true]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 50, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($user)->post("/user/order/{$order->id}/mock-pay")->assertRedirect('/user');

    expect($order->fresh()->status)->toBe('paid');
    $user->refresh();
    expect($user->class)->toBe(2);
    expect($user->transfer_enable)->toBe(300 * 1024 ** 3);
});

it('cannot pay another user order', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);
    $order = Order::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($other)->post("/user/order/{$order->id}/mock-pay")->assertForbidden();
    expect($order->fresh()->status)->toBe('pending');
});
