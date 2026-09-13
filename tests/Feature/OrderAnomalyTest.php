<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrderAnomalies;

/**
 * 订单异常告警。
 *
 * `[!!]` 判据取自【真实流程不会产生的组合】（读 BillingService 得到的不变量），
 * 不是泛泛的"已付未发货"。排队中的订单不是异常 —— 当前套餐没到期就该排队，
 * 把它算进去等于天天误报，而天天误报的告警等于没有告警。
 */
$GLOBALS['oaSeq'] = 0;

function oaOrder(array $over): Order
{
    $u = User::factory()->create();
    $p = Plan::create([
        'name' => 'P'.(++$GLOBALS['oaSeq']), 'price' => 10, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 0,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30, 'sort' => 0, 'on_sale' => true,
    ]);

    return Order::create(array_merge([
        'user_id' => $u->id, 'plan_id' => $p->id, 'order_no' => 'O'.$GLOBALS['oaSeq'],
        'amount' => 10, 'period' => 'month', 'status' => 'paid',
    ], $over));
}

function oaKeys(): array
{
    return array_column(app(OrderAnomalies::class)->check(), 'key');
}

it('一切正常时不报任何异常', function () {
    oaOrder(['status' => 'paid', 'paid_at' => now(), 'delivered_at' => now(), 'activate_at' => now()]);
    oaOrder(['status' => 'cancelled']);

    expect(oaKeys())->toBe([]);
});

it('排队中的订单不算异常 —— 当前套餐没到期就该排队', function () {
    // `[!!]` 把它算进去等于天天误报。
    oaOrder(['status' => 'queued', 'paid_at' => now(), 'activate_at' => now()->addDays(10)]);

    expect(oaKeys())->toBe([]);
});

it('已付却没有发货记录时报出来', function () {
    oaOrder(['status' => 'paid', 'paid_at' => now(), 'delivered_at' => null]);

    expect(oaKeys())->toContain('paid_undelivered');
});

it('排队订单过了激活时刻还没转正，要报', function () {
    // 这是最可能真实发生的一条：激活任务静默失败时，
    // 用户会在上一个套餐到期后突然没得用，而订单页显示一切正常。
    oaOrder(['status' => 'queued', 'paid_at' => now(), 'activate_at' => now()->subHours(2)]);

    expect(oaKeys())->toContain('queued_stuck');
});

it('刚过激活时刻还在宽限期内的不报 —— 定时任务十分钟一次', function () {
    oaOrder(['status' => 'queued', 'paid_at' => now(), 'activate_at' => now()->subMinutes(5)]);

    expect(oaKeys())->not->toContain('queued_stuck');
});

it('记了付款时间却仍是待支付，要报', function () {
    oaOrder(['status' => 'pending', 'paid_at' => now()]);

    expect(oaKeys())->toContain('pending_with_paid_at');
});

it('长期待支付只是提醒，不是故障', function () {
    $o = oaOrder(['status' => 'pending']);
    // `[!]` 不能用 update()：created_at 不在 fillable 里，会被批量赋值静默丢掉，
    // 于是订单还是"刚创建"，告警不触发 —— 而失败信息看着像被测代码的问题。
    $o->created_at = now()->subDays(3);
    $o->save();

    $found = collect(app(OrderAnomalies::class)->check())->firstWhere('key', 'stale_pending');
    expect($found)->not->toBeNull()
        ->and($found['level'])->toBe('warn');   // 不是 bad
});

it('每一条告警都能点进去看到具体是哪些订单', function () {
    // `[!]` 只报数字不给去处，等于把问题丢回去 —— 人还得自己写 SQL。
    oaOrder(['status' => 'paid', 'paid_at' => now(), 'delivered_at' => null]);
    oaOrder(['status' => 'queued', 'paid_at' => now(), 'activate_at' => now()->subHours(2)]);

    foreach (app(OrderAnomalies::class)->check() as $a) {
        $scope = OrderAnomalies::scope($a['key']);
        expect($scope)->not->toBeNull("告警 {$a['key']} 没有对应的筛选")
            ->and($scope->count())->toBe($a['count'], "告警 {$a['key']} 的数字与筛选结果对不上");
    }
});

it('总览上没有异常时不显示那一块 —— 常驻的"一切正常"横幅没人会看', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    oaOrder(['status' => 'paid', 'paid_at' => now(), 'delivered_at' => now(), 'activate_at' => now()]);

    $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('订单异常');
});

it('有异常时总览上报出来，并能点进订单页看到那一批', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    oaOrder(['status' => 'queued', 'paid_at' => now(), 'activate_at' => now()->subHours(2)]);
    oaOrder(['status' => 'paid', 'paid_at' => now(), 'delivered_at' => now(), 'activate_at' => now()]);

    $this->actingAs($admin)->get('/admin')->assertOk()
        ->assertSee('订单异常')->assertSee('排队订单过了激活时刻还没生效');

    $this->actingAs($admin)->get('/admin/orders?anomaly=queued_stuck')->assertOk()
        // 必须说清楚这是子集,否则人会以为订单只剩这几条
        ->assertSee('不是全部订单');
});

it('异常筛选不受状态筛选干扰 —— 那几种形态本来就是状态不可信', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    oaOrder(['status' => 'queued', 'paid_at' => now(), 'activate_at' => now()->subHours(2)]);

    // 带上一个会把它筛掉的 status，异常筛选仍应生效
    $r = $this->actingAs($admin)->get('/admin/orders?anomaly=queued_stuck&status=cancelled')->assertOk();
    expect($r->viewData('orders')->total())->toBe(1);
});

it('未知的 anomaly 参数退回普通列表，不报错', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    oaOrder(['status' => 'paid', 'paid_at' => now(), 'delivered_at' => now(), 'activate_at' => now()]);

    $this->actingAs($admin)->get('/admin/orders?anomaly=nonsense')->assertOk();
});
