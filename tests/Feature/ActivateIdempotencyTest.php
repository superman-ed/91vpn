<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;

/**
 * 排队订单激活的幂等（L-10）。
 *
 * `[!!]` 此前 activate() 只锁 user、【不复查订单状态】，对同一个订单调两次
 * 会发两次货。同一个类里的 settleOrder() 连调两次是安全的 ——
 * 说明那不是框架限制，是少写了一层复查。
 *
 * `[!]` 顺序跑两次本来就安全（查询条件 status=queued 已不匹配），
 * 所以这一层挡的是【并发】，不是重放。
 * 真并发的验证在 tools/l10/run.sh（两个独立进程）。
 */
$GLOBALS['ai'] = 0;

function aiPlan(int $days = 30): Plan
{
    return Plan::create([
        'name' => 'AI-'.(++$GLOBALS['ai']), 'price' => 30, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 3,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => $days,
        'sort' => 0, 'on_sale' => true, 'stock' => -1, 'is_data_pack' => false,
    ]);
}

function aiQueued(User $u, Plan $p): Order
{
    return Order::create([
        'user_id' => $u->id, 'plan_id' => $p->id, 'amount' => 30,
        'status' => 'queued', 'period' => 'month',
        'order_no' => 'AI-'.$GLOBALS['ai'].'-'.random_int(1000, 9999),
        'activate_at' => now()->subMinute(),
    ]);
}

it('对同一个订单调两次 activate，只发一次货', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->addDays(9)]);
    $order = aiQueued($user, aiPlan(30));
    $billing = app(BillingService::class);

    expect($billing->activate($order))->toBeTrue();
    $after = $user->fresh()->class_expire;

    // 第二次必须被锁内复查挡掉
    expect($billing->activate($order->fresh()))->toBeFalse();
    expect($user->fresh()->class_expire->toDateTimeString())->toBe($after->toDateTimeString());
    expect($order->fresh()->status)->toBe('paid');
});

it('对照：settleOrder 一直是这个套路 —— 说明这不是框架限制', function () {
    $user = User::factory()->create(['money' => 100]);
    $plan = aiPlan();
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 30,
        'status' => 'pending', 'period' => 'month', 'order_no' => 'AI-S1',
    ]);
    $billing = app(BillingService::class);

    expect($billing->settleOrder($order, 'balance'))->toBeTrue();
    expect($billing->settleOrder($order->fresh(), 'balance'))->toBeFalse();
});

it('已经是 paid 的订单不会被再次激活', function () {
    $user = User::factory()->create(['class' => 3, 'class_expire' => now()->addDays(9)]);
    $order = aiQueued($user, aiPlan());
    $order->update(['status' => 'paid', 'delivered_at' => now()]);

    expect(app(BillingService::class)->activate($order->fresh()))->toBeFalse();
});

it('调度命令顺序跑两次仍然只发一次货，且只写一条审计', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => null]);
    aiQueued($user, aiPlan(30));

    AuditLog::query()->delete();
    $this->artisan('orders:activate-due')->assertSuccessful();
    $expire = $user->fresh()->class_expire;
    expect((int) $user->fresh()->class)->toBe(3);

    $this->artisan('orders:activate-due')->assertSuccessful();
    expect($user->fresh()->class_expire->toDateTimeString())->toBe($expire->toDateTimeString());
    expect(AuditLog::where('action', 'order.auto_activate')->count())->toBe(1);
});

it('被跳过时不写审计 —— 记一条"自动发货"是假记录，比不记更糟', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => null]);
    $order = aiQueued($user, aiPlan());

    app(BillingService::class)->activate($order);        // 先由别的路径发掉
    AuditLog::query()->delete();

    $this->artisan('orders:activate-due')->assertSuccessful();
    expect(AuditLog::count())->toBe(0);
});

it('endCurrentPackage 走的是同一条路，仍然只发一次', function () {
    $user = User::factory()->create(['class' => 3, 'class_expire' => now()->addDays(20)]);
    $order = aiQueued($user, aiPlan(30));

    app(BillingService::class)->endCurrentPackage($user->fresh());
    $expire = $user->fresh()->class_expire;
    expect($order->fresh()->status)->toBe('paid');

    // 紧接着调度器也跑了一轮 —— 这正是审计里指出的那条并发路径
    $this->artisan('orders:activate-due')->assertSuccessful();
    expect($user->fresh()->class_expire->toDateTimeString())->toBe($expire->toDateTimeString());
});
