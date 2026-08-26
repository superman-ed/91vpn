<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;

/**
 * 加油包(流量包)计费不变量回归矩阵。
 * 口径见 billing-model：加油包立即加流量、不延长到期、不排队；随主套餐配额归位而清零，
 * 但总量型(none)/免费用户没有重置这一步，故加油包在他们身上永久保留。
 */
const GB = 1024 ** 3;

function dpPack(array $attr = []): Plan
{
    return Plan::create(array_merge([
        'name' => '50GB 加油包', 'price' => 15, 'transfer_gb' => 50, 'is_data_pack' => true,
        'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 0, 'on_sale' => true,
    ], $attr));
}

function dpOrder(User $user, Plan $plan): Order
{
    return Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => $plan->price,
        'status' => 'pending', 'period' => 'pack',
    ]);
}

it('买加油包立即加流量，不延长到期、不排队', function () {
    // 已有生效中的月付套餐
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(20),
        'next_reset_at' => now()->addDays(20),
        'base_transfer_enable' => 100 * GB, 'transfer_enable' => 100 * GB,
    ]);
    $expireBefore = $user->class_expire->copy();

    app(BillingService::class)->completeOrder(dpOrder($user, dpPack()), 'mock');

    $user->refresh();
    expect($user->transfer_enable)->toBe(150 * GB);                 // 立即 +50
    expect($user->class_expire->eq($expireBefore))->toBeTrue();     // 到期日不变
    expect($user->orders()->where('status', 'queued')->count())->toBe(0);   // 不排队
    expect($user->orders()->where('status', 'paid')->first()->delivered_at)->not->toBeNull();
});

it('月付用户到重置日：加油包被清零，同批总量型用户不受影响', function () {
    $monthly = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(60), 'next_reset_at' => now()->subMinute(),
        'base_transfer_enable' => 100 * GB, 'transfer_enable' => 150 * GB,   // 100 基础 + 50 加油包
    ]);
    // 总量型：next_reset_at 为 null，压根不进重置查询
    $total = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(60), 'next_reset_at' => null,
        'base_transfer_enable' => 120 * GB, 'transfer_enable' => 170 * GB,   // 120 总量 + 50 加油包
    ]);

    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    expect($monthly->fresh()->transfer_enable)->toBe(100 * GB);     // 加油包随归位被清
    expect($total->fresh()->transfer_enable)->toBe(170 * GB);       // 总量型不重置，加油包保留
});

it('总量型套餐用户的加油包：跑完重置仍完整保留', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(90), 'next_reset_at' => null,
        'base_transfer_enable' => 120 * GB, 'transfer_enable' => 120 * GB,
    ]);
    app(BillingService::class)->applyDataPack($user, dpPack(['transfer_gb' => 30]));
    expect($user->fresh()->transfer_enable)->toBe(150 * GB);

    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    expect($user->fresh()->transfer_enable)->toBe(150 * GB);        // 永不重置 → 永久保留
});

it('免费(class=0)用户的加油包：不被月度重置触碰', function () {
    $user = User::factory()->create([
        'class' => 0, 'class_expire' => null, 'next_reset_at' => null,
        'base_transfer_enable' => 0, 'transfer_enable' => 0,
    ]);
    app(BillingService::class)->applyDataPack($user, dpPack(['transfer_gb' => 20]));
    expect($user->fresh()->transfer_enable)->toBe(20 * GB);

    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    expect($user->fresh()->transfer_enable)->toBe(20 * GB);         // 重置只扫 class>0，免费用户不动
});
