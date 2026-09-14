<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;

/**
 * 审计实验 P1-4 AuditLog 完整性 —— 只做观察，不修复。
 *
 * 两个方向：
 *   (a) 写进日志的事，是不是真发生了
 *   (b) 真发生的事，是不是都写进了日志   ← 本文件的重点
 *
 * `[!!]` 只数「有多少个后台控制器调了 audit()」会得出"覆盖完整"的结论，
 * 而那个数字回答的是 (b) 的一半：它只覆盖【人点出来的】变更。
 * 定时任务同样在改用户的等级、配额、订单状态，一条都不写。
 */
$GLOBALS['p14'] = 0;

function p14Plan(array $over = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'P14-'.(++$GLOBALS['p14']), 'price' => 100, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 3,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1, 'is_data_pack' => false,
    ], $over));
}

// ─────────────────────────────────────────────────────────────────
// P14-A 定时任务改掉用户权益，一条审计都不留
// ─────────────────────────────────────────────────────────────────
it('P14-A 月度重置抹掉已付费的加油包，审计日志里没有任何记录', function () {
    $gb = 1024 ** 3;
    $user = User::factory()->create();
    $billing = app(BillingService::class);
    $billing->deliver($user, p14Plan());
    $billing->applyDataPack($user, p14Plan(['transfer_gb' => 50, 'is_data_pack' => true]));
    $user->update(['next_reset_at' => now()->subMinute()]);

    AuditLog::query()->delete();                  // 只看这一步产生了什么
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    $user->refresh();
    expect((int) $user->transfer_enable)->toBe(100 * $gb);   // 50GB 确实没了（P11-A）

    // `[!!]` 而系统里【没有任何地方】记下这件事发生过。
    // 用户问「我买的 50GB 去哪了」时，能拿出来的证据是零。
    expect(AuditLog::count())->toBe(0);
});

it('P14-A2 排队订单自动发货，同样不留痕', function () {
    $user = User::factory()->create(['class' => 0]);
    $plan = p14Plan();
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 100,
        'status' => 'queued', 'period' => 'month', 'order_no' => 'P14A2',
        'activate_at' => now()->subMinute(),
    ]);

    AuditLog::query()->delete();
    $this->artisan('orders:activate-due')->assertSuccessful();

    expect($order->fresh()->status)->toBe('paid');
    expect((int) $user->fresh()->class)->toBe(3);          // 等级真的变了
    expect(AuditLog::count())->toBe(0);                    // 而没有记录
});

it('P14-A3 自动关单改的是订单状态，也没有记录', function () {
    $user = User::factory()->create();
    $plan = p14Plan();
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 100,
        'status' => 'pending', 'period' => 'month', 'order_no' => 'P14A3',
        'pay_method' => 'balance',      // 非网关订单，不会去查网关
    ]);
    // `[!]` created_at 不能走 create() —— 它不在 $fillable 里，会被静默丢掉，
    // 于是订单看起来是"刚下的"，关单命令一条都不处理，断言变成空真。
    // (这个坑本项目踩过一次，见 OrderAnomalies 的测试。)
    $order->forceFill(['created_at' => now()->subDays(3)])->save();

    AuditLog::query()->delete();
    $this->artisan('orders:expire-pending')->assertSuccessful();

    // 前置条件：这一轮确实动了这张订单，否则下面那条断言是空真
    expect($order->fresh()->status)->toBe('cancelled');
    expect(AuditLog::count())->toBe(0);
});

it('P14-A4 对照：同样的变更由人点出来时，是有记录的', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['class' => 0, 'money' => 0]);

    $this->actingAs($admin);
    AuditLog::query()->delete();
    app(BillingService::class)->adminAdjust($user, 50.0, 'auditor');
    // 后台改用户会 audit('user.update', ...)，这里直接走控制器更实在
    $this->put("/admin/users/{$user->id}", [
        'class' => 3, 'transfer_enable_gb' => 100, 'money' => 50, 'original_money' => 50,
    ]);

    expect((int) $user->fresh()->class)->toBe(3);
    $log = AuditLog::latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('user.update');
    expect($log->admin_id)->toBe($admin->id);
    // 有这一条，上面三条才说明问题：不是"这个系统不写审计"，
    // 是"只有人点的那部分写"。
});

// ─────────────────────────────────────────────────────────────────
// P14-B 「系统」这个署名目前只可能来自被删掉的管理员
// ─────────────────────────────────────────────────────────────────
it('P14-B admin_id 为空时页面显示「系统」，而没有任何自动任务会产生它', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    AuditLog::create([
        'action' => 'user.update', 'description' => 'P14B 无署名记录',
        'admin_id' => null, 'ip' => '',
    ]);

    $this->get('/admin/system/audit')
        ->assertOk()
        ->assertSee('P14B 无署名记录')
        ->assertSee('系统');

    // `[!]` 而 admin_id=null 有两个来源：自动任务，和【管理员被删掉】。
    // 由于没有任何自动任务写审计（P14-A），今天这个标签只可能是后者 ——
    // 也就是说它在说「这条记录的操作人已经不在了」，而不是「这是系统干的」。
});
