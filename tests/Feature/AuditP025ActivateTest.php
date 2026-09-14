<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;

/**
 * 审计实验 P02-5 —— 只做观察，不修复。
 *
 * 待查命题：BillingService::activate() 只锁 user 行，【不锁订单、不复查订单状态】，
 * 所以对同一个订单调用两次会发两次货（时长加两个周期）。
 *
 * `[!!]` 必须同时验【两件事】，否则会把结论夸大：
 *   (a) 服务层是否真的不复查状态 —— 缺陷本身
 *   (b) `orders:activate-due` 顺序跑两次是否安全 —— 常见路径是否已被查询条件挡住
 * 只验 (a) 会得出"每十分钟就可能重复发货"的错误结论。
 */
$GLOBALS['p25'] = 0;

function p25Plan(int $days = 30, int $gb = 100, int $class = 3): Plan
{
    return Plan::create([
        'name' => 'P25-'.(++$GLOBALS['p25']), 'price' => 100, 'period' => 'month',
        'transfer_gb' => $gb, 'reset_type' => 'monthly', 'class' => $class,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => $days,
        'sort' => 0, 'on_sale' => true, 'stock' => -1,   // -1 = 不限量
    ]);
}

/** 造一笔【已排队且到点】的订单：用户当前套餐未到期 → 购买后进 queued。 */
function p25QueuedOrder(int $days = 30): array
{
    $user = User::factory()->create([
        'is_admin' => false, 'class' => 3,
        'class_expire' => now()->addDays(10),
        'transfer_enable' => 100 * 1024 ** 3, 'base_transfer_enable' => 100 * 1024 ** 3,
    ]);
    $plan = p25Plan($days);
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_no' => 'P25-'.$GLOBALS['p25'],
        'amount' => 100, 'period' => 'month', 'status' => 'pending',
    ]);
    app(BillingService::class)->completeOrder($order, 'epay');
    $order = $order->fresh();
    // 到点
    $order->update(['activate_at' => now()->subMinute()]);

    return [$user, $plan, $order->fresh()];
}

function p25Days(User $u): int
{
    return (int) now()->diffInDays($u->fresh()->class_expire, false);
}

it('前提成立：购买后确实进入 queued，且现有权益未动', function () {
    [$user, , $order] = p25QueuedOrder();
    expect($order->status)->toBe('queued')
        ->and($order->activate_at)->not->toBeNull()
        ->and(p25Days($user))->toBe(9);   // 原有 10 天，未被改动
});

it('`[D]` (a) activate() 对同一订单调两次 → 发两次货', function () {
    [$user, , $order] = p25QueuedOrder(days: 30);
    $billing = app(BillingService::class);
    $before = p25Days($user);

    $billing->activate($order);
    $after1 = p25Days($user);

    // `[!!]` 用【同一个实例】再调一次 —— 这正是两轮任务重叠时各自持有的状态：
    // 都在对方提交前读到 queued。不需要构造真并发就能证明"无状态复查"。
    $billing->activate($order);
    $after2 = p25Days($user);

    dump(['剩余天数' => ['起始' => $before, '第一次激活后' => $after1, '第二次激活后' => $after2],
        '订单状态' => $order->fresh()->status]);

    expect($after1)->toBeGreaterThan($before);
    // 命题：第二次仍然生效 → 又加了一个周期
    expect($after2)->toBeGreaterThan($after1);
});

it('`[D]` 对照：settleOrder 同样调两次 → 第二次被拒（同一文件里的正确写法）', function () {
    $user = User::factory()->create(['is_admin' => false, 'class_expire' => now()->subDay()]);
    $plan = p25Plan(days: 30);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id,
        'order_no' => 'P25C', 'amount' => 100, 'period' => 'month', 'status' => 'pending']);
    $billing = app(BillingService::class);

    expect($billing->settleOrder($order, 'epay'))->toBeTrue();
    $after1 = p25Days($user);
    // 同一个陈旧实例再调
    expect($billing->settleOrder($order, 'epay'))->toBeFalse();   // 锁内复查 pending → 拒绝
    expect(p25Days($user))->toBe($after1);
});

it('`[D]` (b) orders:activate-due 顺序跑两次是【安全的】—— 不要夸大结论', function () {
    // 第二轮的查询条件 status=queued 已经不匹配（第一轮把它改成了 paid）。
    // 所以常见路径没有问题，风险只在【两轮重叠】时。
    [$user, , ] = p25QueuedOrder(days: 30);
    $before = p25Days($user);

    $this->artisan('orders:activate-due')->assertExitCode(0);
    $after1 = p25Days($user);
    $this->artisan('orders:activate-due')->assertExitCode(0);
    $after2 = p25Days($user);

    dump(['命令跑两次' => ['起始' => $before, '第一次' => $after1, '第二次' => $after2]]);

    expect($after1)->toBeGreaterThan($before)
        ->and($after2)->toBe($after1);   // 第二次没有再加
});
