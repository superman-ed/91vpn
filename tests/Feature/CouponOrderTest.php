<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

it('creates a discounted order with valid coupon', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $coupon = Coupon::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'max_use' => -1, 'enabled' => true]);

    $this->actingAs($user)->post('/user/order/create', ['plan_id' => $plan->id, 'coupon' => 'SAVE20'])->assertRedirect();

    $order = Order::where('user_id', $user->id)->first();
    expect((float) $order->amount)->toBe(24.0);       // 30 * 0.8
    expect($coupon->fresh()->used)->toBe(1);
});

it('rejects invalid coupon gracefully (full price)', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);

    $this->actingAs($user)->post('/user/order/create', ['plan_id' => $plan->id, 'coupon' => 'NOTEXIST'])
        ->assertSessionHasErrors('coupon');
});

it('admin creates a coupon', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->post('/admin/coupons', [
        'code' => 'NEWYEAR', 'type' => 'percent', 'value' => 15, 'max_use' => 100,
    ])->assertRedirect('/admin/coupons');
    expect(\App\Models\Coupon::where('code', 'NEWYEAR')->exists())->toBeTrue();
});
