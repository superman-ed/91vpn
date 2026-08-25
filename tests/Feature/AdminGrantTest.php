<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('grants a plan to a user and records an admin order', function () {
    $u = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay(), 'transfer_enable' => 0]);
    $plan = Plan::create(['name' => 'VIP②', 'price' => 50, 'period' => 'month', 'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200, 'ip_limit' => 5, 'duration_days' => 30]);

    $this->actingAs($this->admin)->post("/admin/users/{$u->id}/grant", ['plan_id' => $plan->id])->assertRedirect('/admin/users');

    $u->refresh();
    expect($u->class)->toBe(2);
    expect($u->transfer_enable)->toBe(300 * 1024 ** 3);
    expect($u->base_transfer_enable)->toBe(300 * 1024 ** 3);
    expect($u->class_expire->isFuture())->toBeTrue();

    $order = Order::where('user_id', $u->id)->first();
    expect($order->status)->toBe('paid');
    expect($order->pay_method)->toBe('admin');
    expect((float) $order->amount)->toBe(0.0);
});

it('shows the grant page with plans', function () {
    $u = User::factory()->create();
    Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);

    $this->actingAs($this->admin)->get("/admin/users/{$u->id}/grant")->assertOk()->assertSee('VIP①');
});

it('cannot grant to an admin', function () {
    $other = User::factory()->create(['is_admin' => true]);
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);

    $this->actingAs($this->admin)->post("/admin/users/{$other->id}/grant", ['plan_id' => $plan->id])->assertForbidden();
});
