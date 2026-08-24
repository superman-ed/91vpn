<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;

function makePlan(array $attr = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100,
        'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true,
    ], $attr));
}

function paidOrder(User $user, Plan $plan): Order
{
    return Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => $plan->price, 'status' => 'pending', 'period' => $plan->period]);
}

it('delivers immediately when the user has no active package', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay(), 'transfer_enable' => 0]);
    $order = paidOrder($user, makePlan(['transfer_gb' => 100]));

    app(BillingService::class)->completeOrder($order, 'mock');

    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->delivered_at)->not->toBeNull();
    expect($user->fresh()->class)->toBe(1);
    expect($user->fresh()->transfer_enable)->toBe(100 * 1024 ** 3);
});

it('queues a new package when current is still active', function () {
    $future = now()->addDays(10);
    $user = User::factory()->create(['class' => 1, 'class_expire' => $future, 'transfer_enable' => 50 * 1024 ** 3]);
    $order = paidOrder($user, makePlan(['class' => 2, 'transfer_gb' => 300, 'duration_days' => 30]));

    app(BillingService::class)->completeOrder($order, 'mock');

    // 排队：不改当前权益
    expect($order->fresh()->status)->toBe('queued');
    expect($order->fresh()->activate_at->toDateString())->toBe($future->toDateString());
    expect($user->fresh()->class)->toBe(1);                       // 仍是旧套餐
    expect($user->fresh()->transfer_enable)->toBe(50 * 1024 ** 3);
});

it('activates a due queued order via the command', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->subMinute(), 'transfer_enable' => 0]);
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => makePlan(['class' => 2, 'transfer_gb' => 300])->id,
        'amount' => 30, 'status' => 'queued', 'period' => 'month', 'activate_at' => now()->subMinute(),
    ]);

    $this->artisan('orders:activate-due')->assertSuccessful();

    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->delivered_at)->not->toBeNull();
    expect($user->fresh()->class)->toBe(2);
    expect($user->fresh()->transfer_enable)->toBe(300 * 1024 ** 3);
});

it('does not activate a queued order before its time', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(5)]);
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => makePlan()->id,
        'amount' => 30, 'status' => 'queued', 'period' => 'month', 'activate_at' => now()->addDays(5),
    ]);

    $this->artisan('orders:activate-due')->assertSuccessful();

    expect($order->fresh()->status)->toBe('queued');
});

it('applies a data pack immediately without touching expiry or class', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(10), 'transfer_enable' => 100 * 1024 ** 3]);
    $pack = makePlan(['name' => '10GB 流量包', 'is_data_pack' => true, 'transfer_gb' => 10, 'class' => 0, 'duration_days' => 0]);
    $order = paidOrder($user, $pack);

    app(BillingService::class)->completeOrder($order, 'mock');

    expect($order->fresh()->status)->toBe('paid');
    expect($user->fresh()->transfer_enable)->toBe(110 * 1024 ** 3);   // 100 + 10，立即加
    expect($user->fresh()->class)->toBe(1);                           // 等级不变
    expect($user->fresh()->class_expire->toDateString())->toBe(now()->addDays(10)->toDateString());
});

it('sets no monthly reset for total (none) type plans', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()]);
    $plan = makePlan(['reset_type' => 'none', 'period' => 'quarter', 'transfer_gb' => 120, 'duration_days' => 90]);

    app(BillingService::class)->deliver($user, $plan);

    expect($user->fresh()->transfer_enable)->toBe(120 * 1024 ** 3);
    expect($user->fresh()->next_reset_at)->toBeNull();               // 总量型不重置
});
