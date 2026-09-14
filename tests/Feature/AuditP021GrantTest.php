<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * 审计实验 P02-1 —— 只做观察，不修复。
 *
 * 待查命题：管理员「赠送套餐」(doGrant) 绕过 BillingService::completeOrder，
 * 当用户【当前套餐未到期】时，它的行为与正规购买有何不同。
 *
 * `[!!]` 必须做【同场景对照】：同一个起始状态，一边走赠送、一边走正规结算。
 * 只看赠送做了什么，看不出它和应有行为差在哪 —— 而"差在哪"才是审计要的。
 */
$GLOBALS['p21'] = 0;

function p21Plan(string $name, int $class, int $gb, int $days, int $stock = -1): Plan
{
    return Plan::create([
        'name' => $name.(++$GLOBALS['p21']), 'price' => 100, 'period' => 'month',
        'transfer_gb' => $gb, 'reset_type' => 'monthly', 'class' => $class,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => $days,
        'sort' => 0, 'on_sale' => true, 'stock' => $stock,
    ]);
}

/** 一个【当前套餐未到期】的用户：大套餐、还剩 100 天、已用掉一些流量。 */
function p21UserWithActivePlan(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'class' => 9,
        'class_expire' => now()->addDays(100),
        'transfer_enable' => 1024 * 1024 ** 3,   // 1 TB
        'base_transfer_enable' => 1024 * 1024 ** 3,
        'u' => 50 * 1024 ** 3,
        'd' => 30 * 1024 ** 3,
    ]);
}

function p21Snapshot(User $u): array
{
    $u = $u->fresh();

    return [
        'class' => $u->class,
        '到期' => $u->class_expire->format('Y-m-d'),
        '剩余天数' => (int) now()->diffInDays($u->class_expire, false),
        '配额GB' => (int) round($u->transfer_enable / 1024 ** 3),
        '已用GB' => (int) round(($u->u + $u->d) / 1024 ** 3),
    ];
}

it('对照：当前套餐未到期时，【正规购买】一个小套餐会排队，不动现有权益', function () {
    $user = p21UserWithActivePlan();
    $small = p21Plan('小套餐', class: 3, gb: 100, days: 30);
    $before = p21Snapshot($user);

    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $small->id, 'order_no' => 'P21A',
        'amount' => 100, 'period' => 'month', 'status' => 'pending',
    ]);
    app(BillingService::class)->completeOrder($order, 'epay');

    dump(['正规购买' => ['改前' => $before, '改后' => p21Snapshot($user),
        '订单状态' => $order->fresh()->status]]);

    // 正规路径：排队，现有权益一动不动
    expect($order->fresh()->status)->toBe('queued');
    expect(p21Snapshot($user))->toBe($before);
});

it('`[D]` 同场景下【管理员赠送】立即覆盖，用户被降级且配额变小', function () {
    $user = p21UserWithActivePlan();
    $small = p21Plan('小套餐', class: 3, gb: 100, days: 30);
    $admin = User::factory()->create(['is_admin' => true, 'admin_role' => 'super']);
    $before = p21Snapshot($user);

    $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($admin)
        ->post("/admin/users/{$user->id}/grant", ['plan_id' => $small->id]);

    $after = p21Snapshot($user);
    dump(['管理员赠送' => ['改前' => $before, '改后' => $after,
        '新订单' => Order::where('pay_method', 'admin')->first()?->only(['status','activate_at'])]]);

    // 观察结果写成断言：等级被改成小套餐的、配额被覆盖成小套餐的
    expect($after['class'])->toBe(3)
        ->and($after['配额GB'])->toBe(100);
});

it('`[D]` 时长是【延长】而不是清零 —— 这一条赠送没有问题', function () {
    $user = p21UserWithActivePlan();
    $small = p21Plan('小套餐', class: 3, gb: 100, days: 30);
    $admin = User::factory()->create(['is_admin' => true, 'admin_role' => 'super']);

    $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($admin)
        ->post("/admin/users/{$user->id}/grant", ['plan_id' => $small->id]);

    // 原剩 100 天 + 30 天
    expect(p21Snapshot($user)['剩余天数'])->toBeGreaterThanOrEqual(129);
});

it('`[D]` 赠送不扣库存，正规购买扣', function () {
    $admin = User::factory()->create(['is_admin' => true, 'admin_role' => 'super']);

    $p1 = p21Plan('限量', class: 3, gb: 100, days: 30, stock: 5);
    $u1 = User::factory()->create(['is_admin' => false, 'class_expire' => now()->subDay()]);
    $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($admin)
        ->post("/admin/users/{$u1->id}/grant", ['plan_id' => $p1->id]);
    $afterGrant = $p1->fresh()->stock;

    $p2 = p21Plan('限量', class: 3, gb: 100, days: 30, stock: 5);
    $u2 = User::factory()->create(['is_admin' => false, 'class_expire' => now()->subDay()]);
    $o = Order::create(['user_id' => $u2->id, 'plan_id' => $p2->id, 'order_no' => 'P21S',
        'amount' => 100, 'period' => 'month', 'status' => 'pending']);
    app(BillingService::class)->completeOrder($o, 'epay');
    $afterBuy = $p2->fresh()->stock;

    dump(['库存 5 → 赠送后' => $afterGrant, '库存 5 → 购买后' => $afterBuy]);

    expect($afterGrant)->toBe(5)   // 未扣
        ->and($afterBuy)->toBe(4); // 扣了
});
