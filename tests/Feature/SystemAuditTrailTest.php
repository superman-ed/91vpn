<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;

/**
 * 定时任务的审计留痕（L-09）。
 *
 * 审计日志此前【只记人点出来的操作】：57 条后台写路由全覆盖，
 * 而定时任务改用户等级、配额、订单状态，一条都不记。
 * 出事时能拿出来的证据是零 —— 见 docs/LAUNCH-CHECKLIST.md L-09。
 *
 * `[!!]` 这里守的主要是【口径】，不是代码分支：
 *   · 只在确实拿走了东西时记（全记会把人工操作淹没）
 *   · 动作沿用 user./order. 前缀，人工与自动排在同一条时间线上
 *   · admin_id=null 的两种来源（自动任务 / 管理员被删）必须分得清
 * 口径错了，代码再对，这页日志也帮不上排查。
 */
$GLOBALS['sa'] = 0;

function saPlan(array $over = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'SA-'.(++$GLOBALS['sa']), 'price' => 20, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 3,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1, 'is_data_pack' => false,
    ], $over));
}

function saMember(array $over = []): User
{
    return User::factory()->create(array_merge([
        'class' => 3, 'class_expire' => now()->addYear(),
        'transfer_enable' => 100 * 1024 ** 3, 'base_transfer_enable' => 100 * 1024 ** 3,
        'u' => 0, 'd' => 0, 'next_reset_at' => now()->subMinute(),
    ], $over));
}

// ─────────────────────────────────────────────────────────────────
// 流量重置
// ─────────────────────────────────────────────────────────────────
it('流量包被清零时留下一条记录，且说得出抹掉了多少', function () {
    $gb = 1024 ** 3;
    $user = saMember(['u' => 100 * $gb]);
    app(BillingService::class)->applyDataPack($user, saPlan(['transfer_gb' => 50, 'is_data_pack' => true]));
    $user->fresh()->update(['u' => 110 * $gb]);      // 用掉其中 10GB

    AuditLog::query()->delete();
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    $log = AuditLog::where('action', 'user.traffic_reset')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->admin_id)->toBeNull();
    expect($log->isSystem())->toBeTrue();
    expect($log->target_id)->toBe($user->id);

    // 要答得上争议，这四个数缺一不可
    expect($log->description)->toContain($user->ident());
    expect($log->description)->toContain('150.00 GB');   // 重置前配额
    expect($log->description)->toContain('100.00 GB');   // 重置后配额
    expect($log->description)->toContain('50.00 GB');    // 被清掉的流量包
    expect($log->description)->toContain('40.00 GB');    // 清掉时还剩多少没用
});

it('没有东西被拿走时不记 —— 否则人工操作会被每月一轮的噪声淹没', function () {
    $member = saMember();                               // 会员，但没买过流量包
    $free = User::factory()->create([
        'class' => 0, 'class_expire' => null,
        'transfer_enable' => 5 * 1024 ** 3, 'base_transfer_enable' => 0,
        'u' => 3 * 1024 ** 3, 'next_reset_at' => now()->subMinute(),
    ]);

    AuditLog::query()->delete();
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    // 两人都确实被刷新了（前置条件），但都没被拿走东西
    expect((int) $member->fresh()->u)->toBe(0);
    expect((int) $free->fresh()->u)->toBe(0);
    expect((int) $free->fresh()->transfer_enable)->toBe(5 * 1024 ** 3);  // 免费用户额度不动
    expect(AuditLog::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────
// 订单
// ─────────────────────────────────────────────────────────────────
it('排队订单自动发货留下记录，写明等级与到期日怎么变的', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => null]);
    $plan = saPlan();
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 20,
        'status' => 'queued', 'period' => 'month', 'order_no' => 'SA-Q1',
        'activate_at' => now()->subMinute(),
    ]);

    AuditLog::query()->delete();
    $this->artisan('orders:activate-due')->assertSuccessful();
    expect((int) $user->fresh()->class)->toBe(3);

    $log = AuditLog::where('action', 'order.auto_activate')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('SA-Q1');
    expect($log->description)->toContain('等级 0 → 3');
    expect($log->target_type)->toBe('Order');
    expect($log->target_id)->toBe($order->id);
});

it('自动关单留下记录，写明是按什么规则关的', function () {
    $user = User::factory()->create();
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => saPlan()->id, 'amount' => 20,
        'status' => 'pending', 'period' => 'month', 'order_no' => 'SA-C1',
        'pay_method' => 'balance',
    ]);
    $order->forceFill(['created_at' => now()->subDays(3)])->save();

    AuditLog::query()->delete();
    $this->artisan('orders:expire-pending')->assertSuccessful();
    expect($order->fresh()->status)->toBe('cancelled');

    $log = AuditLog::where('action', 'order.auto_cancel')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('SA-C1');
    expect($log->description)->toContain('未支付');
});

// ─────────────────────────────────────────────────────────────────
// 口径守卫
// ─────────────────────────────────────────────────────────────────
it('未登记的系统动作直接抛错，而不是悄悄写进去', function () {
    expect(fn () => system_audit('order.something_new', 'x'))
        ->toThrow(InvalidArgumentException::class);

    // `[!]` 漏登记不会报错、只会让那条记录在页面上显示成"管理员被删了"——
    // 一条会误导排查的假信息。所以宁可在写入时就炸。
    expect(AuditLog::where('action', 'order.something_new')->count())->toBe(0);
});

it('每个定时任务用到的动作都登记在 SYSTEM_ACTIONS 里', function () {
    $used = [];
    foreach (glob(base_path('app/Console/Commands/*.php')) as $f) {
        preg_match_all("/system_audit\(\s*'([^']+)'/", file_get_contents($f), $m);
        $used = array_merge($used, $m[1]);
    }
    expect($used)->not->toBe([]);          // 反向对照：确实扫到了东西

    // `[!]` 收集完再断言 —— toContain($x, $msg) 的第二个参数不是消息（判据 74）
    $missing = array_values(array_diff(array_unique($used), array_keys(AuditLog::SYSTEM_ACTIONS)));
    expect($missing)->toBe([]);
});

it('每个系统动作的前缀都有对应的筛选分组 —— 否则按分组筛时它们看不见', function () {
    $groups = array_keys(\App\Http\Controllers\Admin\AuditLogController::GROUPS);
    $missing = [];
    foreach (array_keys(AuditLog::SYSTEM_ACTIONS) as $action) {
        $prefix = explode('.', $action)[0];
        if (! in_array($prefix, $groups, true)) {
            $missing[] = $action;
        }
    }
    expect($missing)->toBe([]);
});

// ─────────────────────────────────────────────────────────────────
// 消费端：日志页
// ─────────────────────────────────────────────────────────────────
it('日志页把「系统」和「已删除的管理员」分开显示', function () {
    // (a) 定时任务写的
    $user = saMember(['u' => 100 * 1024 ** 3]);
    app(BillingService::class)->applyDataPack($user, saPlan(['transfer_gb' => 50, 'is_data_pack' => true]));
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    // (b) 人工写的，然后把那个管理员删掉
    $admin = User::factory()->create(['is_admin' => true]);
    $victim = User::factory()->create(['class' => 0, 'money' => 0]);
    $this->actingAs($admin);
    $this->put("/admin/users/{$victim->id}", [
        'class' => 1, 'transfer_enable_gb' => 10, 'money' => 0, 'original_money' => 0,
    ])->assertRedirect();
    $admin->delete();

    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin/system/audit')->assertOk()->getContent();

    expect($html)->toContain('流量重置');            // 动作名从 SYSTEM_ACTIONS 取得到
    expect($html)->toContain('系统');
    expect($html)->toContain('已删除的管理员');
});

it('来源筛选按【动作名】分，不按 admin_id 是否为空', function () {
    $user = saMember(['u' => 100 * 1024 ** 3]);
    app(BillingService::class)->applyDataPack($user, saPlan(['transfer_gb' => 50, 'is_data_pack' => true]));
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    $admin = User::factory()->create(['is_admin' => true]);
    $victim = User::factory()->create(['class' => 0, 'money' => 0]);
    $this->actingAs($admin);
    $this->put("/admin/users/{$victim->id}", [
        'class' => 1, 'transfer_enable_gb' => 10, 'money' => 0, 'original_money' => 0,
    ])->assertRedirect();
    // `[!!]` 先把重定向留下的 flash 消费掉。
    // 后台改用户会 with('status', "已更新用户 …")，这条闪存会挂到【下一个】请求上 ——
    // 于是审计页上会出现"更新用户"四个字，而它来自顶部的提示条，不是任何一行日志。
    // 不消费的话下面两条断言测的是 flash，不是筛选，而且方向正好相反。
    // （同一类坑本轮踩过第二次：上一次是搜索框把 q 原样回显。）
    $this->get('/admin/users');
    $admin->delete();                       // 这条日志的 admin_id 也变成 null 了

    $me = User::factory()->create(['is_admin' => true]);

    $sys = $this->actingAs($me)->get('/admin/system/audit?src=system')->assertOk()->getContent();
    expect($sys)->toContain('流量重置');
    expect($sys)->not->toContain('更新用户');

    $human = $this->actingAs($me)->get('/admin/system/audit?src=human')->assertOk()->getContent();
    // `[!!]` 关键：这条人工记录的 admin_id 也是 null（管理员被删了），
    // 按 admin_id 分会把它错划成"系统"。按动作名分才对。
    expect($human)->toContain('更新用户');
    expect($human)->not->toContain('流量重置');
});
