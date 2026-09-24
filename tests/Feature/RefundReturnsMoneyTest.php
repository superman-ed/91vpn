<?php

use App\Models\BalanceLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use App\Services\RefundService;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 2026-09-24 实测:余额支付的订单退款后【钱没回来】——
//     支付前 100 → 余额支付 30 → 退款 ¥30 → 余额仍是 70
//   而系统返回 ok 并显示「已退款 ¥30.00」,订单 refunded、refund_amount=30,
//   BalanceLog 只有一条 consume、没有任何反向流水。
//   RefundService 的文档注释写着"这个服务只保证钱这一侧精确" —— 那句话是假的,
//   而钱恰恰是它静默什么都不做的那一侧。
//   caveats()(那个专门"把做不到的事明说出来"的清单)也一个字都没提。
// ─────────────────────────────────────────────────────────────────

function rfUser(float $money = 100): User
{
    return User::factory()->create([
        'password' => 'x12345678', 'money' => $money, 'class' => 0,
        'transfer_enable' => 0, 'u' => 0, 'd' => 0,
    ]);
}

function rfPlan(): Plan
{
    return Plan::create([
        'name' => '测试月付', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100,
        'class' => 1, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1,
    ]);
}

function rfOrder(User $u, Plan $p, string $no): Order
{
    return Order::create([
        'user_id' => $u->id, 'plan_id' => $p->id, 'amount' => 30,
        'status' => 'pending', 'period' => 'month', 'order_no' => $no,
    ]);
}

it('余额支付的订单退款会把钱退回余额', function () {
    $u = rfUser(100);
    $o = rfOrder($u, rfPlan(), 'RF-BAL');

    app(BillingService::class)->payWithBalance($o);
    expect((float) $u->fresh()->money)->toBe(70.0);   // 先证明确实扣过

    $r = app(RefundService::class)->refund($o->fresh(), 30.0, '用户要求退款', false);

    expect($r['ok'])->toBeTrue();
    expect((float) $u->fresh()->money)->toBe(100.0, '退款后余额没有回到支付前');
});

// `[!]` P11-D:改余额的每条路径都要在同一事务里写 BalanceLog,
//   balance_after 取行锁内的值。退款这条也必须守这个规矩。
it('退款会记一条可对账的退款流水', function () {
    $u = rfUser(100);
    $o = rfOrder($u, rfPlan(), 'RF-LOG');
    app(BillingService::class)->payWithBalance($o);
    app(RefundService::class)->refund($o->fresh(), 30.0, '不好用', false);

    $log = BalanceLog::where('user_id', $u->id)->where('type', 'refund')->first();
    expect($log)->not->toBeNull('没有退款流水 —— 账对不上');
    expect((float) $log->amount)->toBe(30.0);
    expect((float) $log->balance_after)->toBe(100.0);
    expect($log->remark)->toContain('RF-LOG');

    // 流水加总要等于最终余额
    expect(round((float) BalanceLog::where('user_id', $u->id)->sum('amount'), 2))
        ->toBe(0.0, 'consume -30 与 refund +30 应当抵消');
});

it('部分退款只退部分', function () {
    $u = rfUser(100);
    $o = rfOrder($u, rfPlan(), 'RF-PART');
    app(BillingService::class)->payWithBalance($o);

    app(RefundService::class)->refund($o->fresh(), 10.0, '部分退', false);

    expect((float) $u->fresh()->money)->toBe(80.0);
});

// `[!!]` 网关支付的钱不在系统内,这里退不了 —— 但必须【说出来】,
//   否则管理员点完会以为事情办完了。
it('网关支付不动余额，但明确告诉管理员要去网关退', function () {
    $u = rfUser(100);
    $o = rfOrder($u, rfPlan(), 'RF-GW');
    $o->update(['status' => 'paid', 'pay_method' => 'epay', 'paid_at' => now(), 'delivered_at' => now()]);

    $r = app(RefundService::class)->refund($o->fresh(), 30.0, '网关退款', false);

    expect((float) $u->fresh()->money)->toBe(100.0);           // 不动余额
    expect(BalanceLog::where('user_id', $u->id)->count())->toBe(0);
    expect($r['message'])->toContain('余额未变动')->toContain('网关');
});

// `[!!]` caveats() 的全部意义就是"把做不到的事明说出来"。
//   钱这一条排最前面 —— 它是管理员最可能误以为系统会办的事。
it('退款前的提示第一条就说清钱怎么处理', function () {
    $u = rfUser(100);
    $bal = rfOrder($u, rfPlan(), 'RF-C1');
    $bal->update(['status' => 'paid', 'pay_method' => 'balance', 'delivered_at' => now()]);
    expect(RefundService::caveats($bal->fresh())[0])->toContain('余额支付')->toContain('退回用户余额');

    $gw = rfOrder($u, rfPlan(), 'RF-C2');
    $gw->update(['status' => 'paid', 'pay_method' => 'epay', 'delivered_at' => now()]);
    expect(RefundService::caveats($gw->fresh())[0])->toContain('不会')->toContain('支付网关后台');
});
