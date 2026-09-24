<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Validation\ValidationException;

// ─────────────────────────────────────────────────────────────────
// `[!!]` settleOrder 里"套餐已下架/售罄就拒绝"这一条,对【网关回调】是错的:
//   用户在网关付完钱,期间管理员按了「下架」(或限量套餐被别人买空),
//   回调走到这里抛异常 —— 订单停在 pending,而钱已经在网关收了。
//   ReconcilePayments 还会反复重试、反复抛。
//   [D] 2026-09-24 实测两种情形都是这个结果。
//
// `[!]` 而"下架"是后台一键按钮、"售罄"是限量套餐的自然结果,都不罕见 ——
//   这比同一函数里优惠券那条更容易发生。
// ─────────────────────────────────────────────────────────────────

function pgPlan(array $over = []): Plan
{
    static $i = 0;
    $i++;

    return Plan::create(array_merge([
        'name' => 'PG'.$i, 'price' => 100, 'period' => 'month', 'transfer_gb' => 100,
        'class' => 1, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1,
    ], $over));
}

function pgOrder(Plan $p, string $no, float $money = 1000): array
{
    $u = User::factory()->create(['password' => 'x12345678', 'money' => $money,
        'class' => 0, 'transfer_enable' => 0, 'u' => 0, 'd' => 0]);
    $o = Order::create(['user_id' => $u->id, 'plan_id' => $p->id, 'amount' => 100,
        'status' => 'pending', 'period' => 'month', 'order_no' => $no]);

    return [$u, $o];
}

it('网关已收款时套餐被下架，仍然发货并留痕', function () {
    $plan = pgPlan();
    [$u, $o] = pgOrder($plan, 'PG-OFF');
    $plan->update(['on_sale' => false]);

    $done = app(BillingService::class)->settleOrder($o->fresh(), 'epay', null, true);

    expect($done)->toBeTrue();
    expect($o->fresh()->status)->toBeIn(['paid', 'queued'], '网关已收款却没有发货');
    expect((string) $o->fresh()->refund_reason)->toContain('已下架或售罄');
    expect($u->fresh()->class)->toBe(1, '权益没有发下去');
});

it('网关已收款时套餐售罄，同样发货', function () {
    $plan = pgPlan(['stock' => 1]);
    [$u, $o] = pgOrder($plan, 'PG-SOLD');
    $plan->update(['stock' => 0]);

    app(BillingService::class)->settleOrder($o->fresh(), 'epay', null, true);

    expect($o->fresh()->status)->toBeIn(['paid', 'queued']);
    expect($u->fresh()->class)->toBe(1);
});

// `[!]` 钱还没收的路径必须保持原样:那正是拒绝的正确时机。
it('钱还没收时，下架的套餐照旧拒绝', function () {
    $plan = pgPlan();
    [$u, $o] = pgOrder($plan, 'PG-BAL');
    $plan->update(['on_sale' => false]);

    expect(fn () => app(BillingService::class)->payWithBalance($o->fresh()))
        ->toThrow(ValidationException::class);

    expect($o->fresh()->status)->toBe('pending');
    expect((float) $u->fresh()->money)->toBe(1000.0, '钱被扣了 —— 事务没回滚');
});

it('正常在售的套餐不受影响', function () {
    $plan = pgPlan();
    [$u, $o] = pgOrder($plan, 'PG-OK');

    app(BillingService::class)->payWithBalance($o->fresh());

    expect($o->fresh()->status)->toBeIn(['paid', 'queued']);
    expect((string) $o->fresh()->refund_reason)->not->toContain('已下架');
});
