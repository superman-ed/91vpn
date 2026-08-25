<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

it('computes range revenue from date filter', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $u = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);

    // 今天的已支付订单
    $o = Order::create(['user_id' => $u->id, 'plan_id' => $plan->id, 'amount' => 88, 'status' => 'paid', 'period' => 'month']);
    $o->update(['paid_at' => now()]);

    $res = $this->actingAs($admin)->get('/admin?from='.now()->toDateString().'&to='.now()->toDateString())->assertOk();
    expect((float) $res->viewData('rangeRevenue'))->toBe(88.0);
    expect($res->viewData('rangeOrders'))->toBe(1);
});
