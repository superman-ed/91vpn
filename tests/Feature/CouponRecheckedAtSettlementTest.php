<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 券原本【只在附加到订单时校验】,支付时不复查 —— 实测(2026-09-24):
//     限量 1 次的券 → 两单都按折后价 50 成交(原价 100),而 used 只涨到 1
//     过期之后支付  → 仍按 50 成交
//   都不是并发,是纯顺序。
//
// `[!!]` 原来那句原子自增(见 L-06 的注释)确实防住了"计数被刷穿",
//   但【钱不在计数器上】:自增影响 0 行时它不看返回值,订单照样以折后价完成。
//   计数说用了 1 次,钱说用了 2 次。
// ─────────────────────────────────────────────────────────────────

function cpPlan(string $name = 'P'): Plan
{
    return Plan::create(['name' => $name, 'price' => 100, 'period' => 'month',
        'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 0, 'ip_limit' => 0,
        'duration_days' => 30, 'sort' => 0, 'on_sale' => true, 'stock' => -1]);
}

function cpBuyer(): User
{
    static $i = 0;
    $i++;

    return User::factory()->create([
        'username' => 'buyer'.$i, 'password' => 'x12345678', 'money' => 1000,
        'class' => 0, 'transfer_enable' => 0, 'u' => 0, 'd' => 0,
    ]);
}

function cpOrder(User $u, Plan $p, string $no, ?string $code = null): Order
{
    $o = Order::create(['user_id' => $u->id, 'plan_id' => $p->id, 'amount' => 100,
        'status' => 'pending', 'period' => 'month', 'order_no' => $no]);
    if ($code) {
        app(OrderService::class)->applyCoupon($o, $code);
    }

    return $o->fresh();
}

it('限量券用尽后，第二单不能再按折后价成交', function () {
    $plan = cpPlan();
    Coupon::create(['code' => 'ONLYONE', 'type' => 'fixed', 'value' => 50,
        'max_use' => 1, 'used' => 0, 'enabled' => true]);

    [$a, $b] = [cpBuyer(), cpBuyer()];
    $oa = cpOrder($a, $plan, 'C-A', 'ONLYONE');
    $ob = cpOrder($b, $plan, 'C-B', 'ONLYONE');
    expect((float) $oa->amount)->toBe(50.0)->and((float) $ob->amount)->toBe(50.0);

    app(BillingService::class)->payWithBalance($oa);          // 第一单正常
    expect($oa->fresh()->status)->toBe('paid');
    expect(1000 - (float) $a->fresh()->money)->toBe(50.0);

    // 第二单必须被拒
    expect(fn () => app(BillingService::class)->payWithBalance($ob))
        ->toThrow(ValidationException::class);

    expect($ob->fresh()->status)->toBe('pending', '券已用尽却仍然成交了');
    expect((float) $b->fresh()->money)->toBe(1000.0, '钱被扣了 —— 事务没有回滚');
    expect(Coupon::where('code', 'ONLYONE')->first()->used)->toBe(1);
});

it('券过期之后支付会被拒，折扣不再生效', function () {
    $plan = cpPlan('P2');
    Coupon::create(['code' => 'SOON', 'type' => 'fixed', 'value' => 50,
        'max_use' => -1, 'used' => 0, 'enabled' => true, 'expires_at' => now()->addMinutes(5)]);

    $u = cpBuyer();
    $o = cpOrder($u, $plan, 'C-EXP', 'SOON');
    expect((float) $o->amount)->toBe(50.0);

    $this->travel(10)->minutes();

    expect(fn () => app(BillingService::class)->payWithBalance($o->fresh()))
        ->toThrow(ValidationException::class);
    expect((float) $u->fresh()->money)->toBe(1000.0);
});

// `[!!]` 这一条是整组里最要紧的:网关那边钱【已经收了】,
//   抛异常只会回滚数据库、退不了那笔钱 —— 结果是用户付了钱而订单失败。
it('网关回调时券已失效，仍然照常发货并留痕', function () {
    $plan = cpPlan('P3');
    $c = Coupon::create(['code' => 'GONE', 'type' => 'fixed', 'value' => 50,
        'max_use' => 1, 'used' => 0, 'enabled' => true]);

    $u = cpBuyer();
    $o = cpOrder($u, $plan, 'C-GW', 'GONE');
    $c->update(['used' => 1]);   // 期间被别人用光
    expect($c->fresh()->isUsable())->toBeFalse();

    // 第四个参数 true = 钱已在网关收到
    $done = app(BillingService::class)->settleOrder($o->fresh(), 'epay', null, true);

    expect($done)->toBeTrue();
    expect($o->fresh()->status)->toBeIn(['paid', 'queued'], '网关已收款却没有发货');
    expect((string) $o->fresh()->refund_reason)->toContain('优惠券已失效');
    expect(Coupon::find($c->id)->used)->toBe(1, 'used 被多加了一次');
});

it('券正常时照常结算并计数', function () {
    $plan = cpPlan('P4');
    Coupon::create(['code' => 'GOOD', 'type' => 'fixed', 'value' => 50,
        'max_use' => 10, 'used' => 0, 'enabled' => true]);

    $u = cpBuyer();
    $o = cpOrder($u, $plan, 'C-OK', 'GOOD');
    app(BillingService::class)->payWithBalance($o);

    expect($o->fresh()->status)->toBeIn(['paid', 'queued']);
    expect(1000 - (float) $u->fresh()->money)->toBe(50.0);
    expect(Coupon::where('code', 'GOOD')->first()->used)->toBe(1);
});
