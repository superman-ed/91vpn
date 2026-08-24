<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;

function coPlan(array $attr = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100,
        'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true,
    ], $attr));
}

function coPending(User $user, Plan $plan): Order
{
    return Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => $plan->price, 'status' => 'pending', 'period' => $plan->period]);
}

it('cancels a pending order', function () {
    $user = User::factory()->create();
    $order = coPending($user, coPlan());

    $this->actingAs($user)->post("/user/order/{$order->id}/cancel")->assertRedirect('/user/wallet');
    expect($order->fresh()->status)->toBe('cancelled');
});

it('cannot cancel a non-pending order', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'plan_id' => coPlan()->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month']);

    $this->actingAs($user)->post("/user/order/{$order->id}/cancel")->assertForbidden();
});

it('reuses an existing pending order for the same plan instead of stacking', function () {
    $user = User::factory()->create();
    $plan = coPlan();

    $this->actingAs($user)->post('/user/order/create', ['plan_id' => $plan->id])->assertRedirect();
    $this->actingAs($user)->post('/user/order/create', ['plan_id' => $plan->id])->assertRedirect();

    expect(Order::where('user_id', $user->id)->where('status', 'pending')->count())->toBe(1);
});

it('delivers a fully-discounted (free) order without a payment method', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()]);
    $plan = coPlan(['transfer_gb' => 100]);
    $order = coPending($user, $plan);
    Coupon::create(['code' => 'FREE100', 'type' => 'percent', 'value' => 100, 'max_use' => -1, 'enabled' => true]);
    $this->actingAs($user)->post("/user/order/{$order->id}/coupon", ['coupon' => 'FREE100']);
    expect((float) $order->fresh()->amount)->toBe(0.0);

    $this->actingAs($user)->post("/user/order/{$order->id}/pay")->assertRedirect('/user');   // 无 method
    expect($order->fresh()->status)->toBe('paid');
    expect($user->fresh()->class)->toBe(1);
});

it('checkout shows immediate-add wording and no duration row for a data pack', function () {
    $user = User::factory()->create();
    $pack = coPlan(['name' => '50GB 包', 'is_data_pack' => true, 'transfer_gb' => 50, 'duration_days' => 0]);
    $order = coPending($user, $pack);

    $this->actingAs($user)->get("/user/order/{$order->id}")
        ->assertOk()->assertSee('立即叠加 50GB')->assertDontSee('时长');
});

it('checkout warns that a new package will be queued when one is active', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(20)]);
    $order = coPending($user, coPlan(['class' => 2]));

    $this->actingAs($user)->get("/user/order/{$order->id}")
        ->assertOk()->assertSee('排队');
});

it('renders admin-configured buy notice on checkout', function () {
    Setting::put('buy_notice', "第一条自定义须知\n第二条自定义须知");
    $user = User::factory()->create();
    $order = coPending($user, coPlan());

    $this->actingAs($user)->get("/user/order/{$order->id}")
        ->assertOk()->assertSee('第一条自定义须知')->assertSee('第二条自定义须知');
});

it('refuses payment when the plan sold out after ordering', function () {
    $user = User::factory()->create(['money' => 100, 'class' => 0, 'class_expire' => now()->subDay()]);
    $plan = coPlan(['stock' => 1]);
    $order = coPending($user, $plan);
    $plan->update(['stock' => 0]);   // 下单后售罄

    $this->actingAs($user)->post("/user/order/{$order->id}/pay", ['method' => 'balance'])
        ->assertSessionHasErrors('plan_id');
    expect($order->fresh()->status)->toBe('pending');
    expect($user->fresh()->class)->toBe(0);
    expect((float) $user->fresh()->money)->toBe(100.0);   // 未扣款
});

it('refuses payment when the plan goes off sale after ordering', function () {
    $user = User::factory()->create(['money' => 100]);
    $plan = coPlan();
    $order = coPending($user, $plan);
    $plan->update(['on_sale' => false]);

    $this->actingAs($user)->post("/user/order/{$order->id}/pay", ['method' => 'balance'])
        ->assertSessionHasErrors('plan_id');
    expect($order->fresh()->status)->toBe('pending');
});

it('settleOrder is idempotent and does not double-deliver or double-decrement stock', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()]);
    $plan = coPlan(['stock' => 5]);
    $order = coPending($user, $plan);
    $billing = app(App\Services\BillingService::class);

    expect($billing->settleOrder($order, 'mock'))->toBeTrue();
    expect($billing->settleOrder($order->fresh(), 'mock'))->toBeFalse();   // 已支付，幂等跳过

    expect($user->fresh()->class)->toBe(1);
    expect($plan->fresh()->stock)->toBe(4);   // 只扣一次库存
});

it('admin updates the buy notice setting', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->put('/admin/settings', ['buy_notice' => "新须知A\n新须知B"])->assertRedirect('/admin/settings');
    expect(Setting::get('buy_notice'))->toBe("新须知A\n新须知B");
});
