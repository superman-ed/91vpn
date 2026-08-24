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

it('rejects a coupon whose period does not match the order', function () {
    $user = User::factory()->create();
    // 月付订单
    $monthly = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $order = pendingOrder($user, $monthly);
    // 仅限半年/年付的券
    Coupon::create(['code' => 'HALFYEAR', 'type' => 'percent', 'value' => 5, 'periods' => ['half_year', 'year'], 'max_use' => -1, 'enabled' => true]);

    $this->actingAs($user)->post("/user/order/{$order->id}/coupon", ['coupon' => 'HALFYEAR'])
        ->assertSessionHasErrors('coupon');
    expect($order->fresh()->coupon_id)->toBeNull();
    expect((float) $order->fresh()->amount)->toBe(30.0);
});

it('applies a period-restricted coupon when the order period matches', function () {
    $user = User::factory()->create();
    $halfYear = Plan::create(['name' => 'VIP①', 'price' => 160, 'period' => 'half_year', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 180, 'on_sale' => true]);
    $order = pendingOrder($user, $halfYear);
    Coupon::create(['code' => 'HALFYEAR', 'type' => 'percent', 'value' => 5, 'periods' => ['half_year', 'year'], 'max_use' => -1, 'enabled' => true]);

    $this->actingAs($user)->post("/user/order/{$order->id}/coupon", ['coupon' => 'HALFYEAR'])->assertRedirect("/user/order/{$order->id}");
    expect((float) $order->fresh()->amount)->toBe(152.0);   // 160 * 0.95
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

it('admin creates a coupon with a checkout note', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->post('/admin/coupons', [
        'code' => 'HALF95', 'note' => 'VIP ①②③ 半年套餐 95 折优惠码', 'type' => 'percent', 'value' => 5, 'max_use' => 100, 'show_on_checkout' => 1,
    ])->assertRedirect('/admin/coupons');

    $c = \App\Models\Coupon::where('code', 'HALF95')->first();
    expect($c)->not->toBeNull();
    expect($c->note)->toBe('VIP ①②③ 半年套餐 95 折优惠码');
    expect($c->show_on_checkout)->toBeTrue();
});

it('admin edits a coupon', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $coupon = Coupon::create(['code' => 'HALF95', 'note' => '旧文案', 'type' => 'percent', 'value' => 5, 'max_use' => -1, 'enabled' => true, 'show_on_checkout' => false]);

    $this->actingAs($admin)->get("/admin/coupons/{$coupon->id}/edit")->assertOk()->assertSee('HALF95');

    $this->actingAs($admin)->put("/admin/coupons/{$coupon->id}", [
        'code' => 'HALF95', 'note' => '新文案', 'type' => 'percent', 'value' => 8, 'periods' => ['half_year', 'year'], 'max_use' => 50, 'enabled' => 0, 'show_on_checkout' => 1,
    ])->assertRedirect('/admin/coupons');

    $coupon->refresh();
    expect($coupon->note)->toBe('新文案');
    expect((float) $coupon->value)->toBe(8.0);
    expect($coupon->periods)->toBe(['half_year', 'year']);
    expect($coupon->max_use)->toBe(50);
    expect($coupon->enabled)->toBeFalse();
    expect($coupon->show_on_checkout)->toBeTrue();
});

it('checkout lists only coupons flagged for display', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $order = pendingOrder($user, $plan);

    Coupon::create(['code' => 'HALF95', 'note' => 'VIP ①②③ 半年套餐 95 折优惠码', 'type' => 'percent', 'value' => 5, 'max_use' => -1, 'enabled' => true, 'show_on_checkout' => true]);
    Coupon::create(['code' => 'SECRET', 'note' => '内部券', 'type' => 'percent', 'value' => 50, 'max_use' => -1, 'enabled' => true, 'show_on_checkout' => false]);
    Coupon::create(['code' => 'USEDUP', 'note' => '已用完', 'type' => 'percent', 'value' => 10, 'max_use' => 1, 'used' => 1, 'enabled' => true, 'show_on_checkout' => true]);

    $res = $this->actingAs($user)->get("/user/order/{$order->id}")->assertOk();
    $res->assertSee('VIP ①②③ 半年套餐 95 折优惠码')->assertSee('HALF95');
    $res->assertDontSee('SECRET')->assertDontSee('USEDUP');   // 未勾选展示 / 已用完不显示
});
