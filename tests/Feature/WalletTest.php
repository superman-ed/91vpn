<?php

use App\Models\BalanceLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

it('shows wallet with balance and orders', function () {
    $user = User::factory()->create(['money' => 25.00]);
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);
    Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($user)->get('/user/wallet')
        ->assertOk()->assertSee('25.00')->assertSee('VIP①');
});

it('paginates purchase records 10 per page', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);
    foreach (range(1, 12) as $i) {
        Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month']);
    }

    $res = $this->actingAs($user)->get('/user/wallet')->assertOk();
    expect($res->viewData('orders')->count())->toBe(10);          // 每页 10 条
    expect($res->viewData('orders')->hasPages())->toBeTrue();
    $res->assertSee('op=2');                                       // 有第二页链接
});

it('mock-recharges balance and logs it', function () {
    $user = User::factory()->create(['money' => 0]);

    $this->actingAs($user)->post('/user/wallet/recharge', ['amount' => 50])->assertRedirect();

    expect((float) $user->fresh()->money)->toBe(50.0);
    $log = BalanceLog::where('user_id', $user->id)->first();
    expect($log->type)->toBe('recharge');
    expect((float) $log->balance_after)->toBe(50.0);
});

it('pays an order with balance and delivers', function () {
    $user = User::factory()->create(['money' => 100, 'class' => 0, 'class_expire' => now()->subDay(), 'transfer_enable' => 0]);
    $plan = Plan::create(['name' => 'VIP②', 'price' => 50, 'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200, 'ip_limit' => 7, 'duration_days' => 30]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 50, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($user)->post("/user/order/{$order->id}/pay-balance")->assertRedirect('/user');

    expect($order->fresh()->status)->toBe('paid');
    $user->refresh();
    expect((float) $user->money)->toBe(50.0);        // 100 - 50
    expect($user->class)->toBe(2);
});

it('rejects balance payment when insufficient', function () {
    $user = User::factory()->create(['money' => 10]);
    $plan = Plan::create(['name' => 'VIP②', 'price' => 50, 'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200, 'ip_limit' => 7, 'duration_days' => 30]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 50, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($user)->post("/user/order/{$order->id}/pay-balance")->assertSessionHasErrors();
    expect($order->fresh()->status)->toBe('pending');
});
