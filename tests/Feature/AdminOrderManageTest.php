<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

function aoPlan(): Plan
{
    return Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);
}

it('marks a pending order paid and delivers', function () {
    $u = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()]);
    $order = Order::create(['user_id' => $u->id, 'plan_id' => aoPlan()->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/mark-paid")->assertRedirect();

    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->pay_method)->toBe('manual');
    expect($u->fresh()->class)->toBe(1);
});

it('cancels a pending order', function () {
    $u = User::factory()->create();
    $order = Order::create(['user_id' => $u->id, 'plan_id' => aoPlan()->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/cancel")->assertRedirect();
    expect($order->fresh()->status)->toBe('cancelled');
});

it('does not mark a non-pending order', function () {
    $u = User::factory()->create();
    $order = Order::create(['user_id' => $u->id, 'plan_id' => aoPlan()->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month']);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/mark-paid");
    expect($order->fresh()->pay_method)->toBeNull();   // 未再次发货
});

it('filters orders by date range', function () {
    $u = User::factory()->create(['email' => 'dated@test.local']);
    $old = Order::create(['user_id' => $u->id, 'plan_id' => aoPlan()->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month']);
    Order::whereKey($old->id)->update(['created_at' => now()->subDays(10)]);
    Order::create(['user_id' => $u->id, 'plan_id' => aoPlan()->id, 'amount' => 66, 'status' => 'paid', 'period' => 'month']);   // 今天

    $res = $this->actingAs($this->admin)->get('/admin/orders?from='.now()->toDateString())->assertOk();
    expect($res->viewData('orders')->total())->toBe(1);   // 只剩今天那单
});

it('computes net profit as revenue minus rebate', function () {
    $u = User::factory()->create();
    Order::create(['user_id' => $u->id, 'plan_id' => aoPlan()->id, 'amount' => 100, 'status' => 'paid', 'period' => 'month', 'paid_at' => now()]);
    \App\Models\Payback::create(['user_id' => $u->id, 'from_user_id' => $u->id, 'amount' => 12.5]);

    $res = $this->actingAs($this->admin)->get('/admin/orders')->assertOk();
    expect((float) $res->viewData('totalRevenue'))->toBe(100.0);
    expect((float) $res->viewData('totalRebate'))->toBe(12.5);
    expect((float) $res->viewData('netProfit'))->toBe(87.5);
});

it('searches orders by user email', function () {
    $u = User::factory()->create(['email' => 'findme@test.local']);
    Order::create(['user_id' => $u->id, 'plan_id' => aoPlan()->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month']);
    $other = User::factory()->create(['email' => 'other@test.local']);
    Order::create(['user_id' => $other->id, 'plan_id' => aoPlan()->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month']);

    $this->actingAs($this->admin)->get('/admin/orders?q=findme')->assertOk()
        ->assertSee('findme@test.local')->assertDontSee('other@test.local');
});
