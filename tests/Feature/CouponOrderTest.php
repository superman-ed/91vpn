<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

function pendingOrder(User $user, Plan $plan): Order
{
    return Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => $plan->price, 'status' => 'pending', 'period' => $plan->period]);
}

it('applies a valid coupon on checkout and counts it only after payment', function () {
    $user = User::factory()->create(['money' => 100]);
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $coupon = Coupon::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'max_use' => -1, 'enabled' => true]);
    $order = pendingOrder($user, $plan);

    // 收银台应用优惠码：抵扣生效，但未支付前不计 used
    $this->actingAs($user)->post("/user/order/{$order->id}/coupon", ['coupon' => 'SAVE20'])->assertRedirect("/user/order/{$order->id}");
    expect((float) $order->fresh()->amount)->toBe(24.0);       // 30 * 0.8
    expect($order->fresh()->coupon_id)->toBe($coupon->id);
    expect($coupon->fresh()->used)->toBe(0);

    // 支付成功后才计入 used，且只按折后价扣款
    $this->actingAs($user)->post("/user/order/{$order->id}/pay-balance")->assertRedirect('/user');
    expect($coupon->fresh()->used)->toBe(1);
    expect((float) $user->fresh()->money)->toBe(76.0);         // 100 - 24
});

it('removes a coupon and restores full price when submitted empty', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $coupon = Coupon::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'max_use' => -1, 'enabled' => true]);
    $order = pendingOrder($user, $plan);

    $this->actingAs($user)->post("/user/order/{$order->id}/coupon", ['coupon' => 'SAVE20']);
    $this->actingAs($user)->post("/user/order/{$order->id}/coupon", ['coupon' => '']);

    expect($order->fresh()->coupon_id)->toBeNull();
    expect((float) $order->fresh()->amount)->toBe(30.0);
});

it('rejects an invalid coupon on checkout and keeps full price', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $order = pendingOrder($user, $plan);

    $this->actingAs($user)->post("/user/order/{$order->id}/coupon", ['coupon' => 'NOTEXIST'])
        ->assertSessionHasErrors('coupon');
    expect((float) $order->fresh()->amount)->toBe(30.0);
});

it('admin creates a coupon', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->post('/admin/coupons', [
        'code' => 'NEWYEAR', 'type' => 'percent', 'value' => 15, 'max_use' => 100,
    ])->assertRedirect('/admin/coupons');
    expect(\App\Models\Coupon::where('code', 'NEWYEAR')->exists())->toBeTrue();
});
