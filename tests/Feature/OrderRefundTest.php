<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\RefundService;

/**
 * 订单退款。
 *
 * `[!!]` 这一组守的是一条界线：**钱这一侧必须准确，权益这一侧不许假装能回滚**。
 * deliver() 是覆盖写，覆盖前的值没有留存 —— 自动还原只能靠猜，
 * 而猜错了不会报错，只会变成一个用户投诉。
 */
$GLOBALS['rfSeq'] = 0;

function rfPlan(array $over = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'P'.(++$GLOBALS['rfSeq']), 'price' => 30, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 1,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => 0,
    ], $over));
}

function rfOrder(array $over = [], ?Plan $plan = null, ?User $user = null): Order
{
    $plan ??= rfPlan();
    $user ??= User::factory()->create();

    return Order::create(array_merge([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_no' => 'R'.$GLOBALS['rfSeq'],
        'amount' => 30, 'period' => 'month', 'status' => 'paid',
        'paid_at' => now(), 'delivered_at' => now(), 'activate_at' => now(),
    ], $over));
}

function rfDo(Order $o, float $amt = 30, string $why = '用户申请', bool $end = false): array
{
    return app(RefundService::class)->refund($o, $amt, $why, $end);
}

it('退款把钱这一侧记准：状态、金额、时间、原因', function () {
    $o = rfOrder();
    expect(rfDo($o, 30, '重复下单')['ok'])->toBeTrue();

    $o = $o->fresh();
    expect($o->status)->toBe('refunded')
        ->and((float) $o->refund_amount)->toBe(30.0)
        ->and($o->refund_reason)->toBe('重复下单')
        ->and($o->refunded_at)->not->toBeNull()
        // `[!]` 原始金额不能被覆盖:"退了多少"与"当初收了多少"是两个事实,对账都要。
        ->and((float) $o->amount)->toBe(30.0);
});

it('允许部分退款', function () {
    $o = rfOrder();
    rfDo($o, 10, '按比例退');
    expect((float) $o->fresh()->refund_amount)->toBe(10.0);
});

it('退款金额不能超过订单金额，也不能是零', function () {
    expect(rfDo(rfOrder(), 100)['ok'])->toBeFalse();
    expect(rfDo(rfOrder(), 0)['ok'])->toBeFalse();
});

it('已取消或已退款的订单不能再退 —— 防重复退款', function () {
    expect(rfDo(rfOrder(['status' => 'cancelled']))['ok'])->toBeFalse();

    $o = rfOrder();
    rfDo($o);
    expect(rfDo($o->fresh())['ok'])->toBeFalse();
});

it('默认【不】撤销权益，并在结果里说明白', function () {
    // `[!!]` 这是整组里最要紧的一条:不假装能回滚。
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(20)]);
    $o = rfOrder([], null, $user);

    $r = rfDo($o);
    expect($r['message'])->toContain('权益【未】撤销');
    // 用户的套餐原样不动
    expect($user->fresh()->class_expire->isFuture())->toBeTrue();
});

it('显式勾选时才结束当前套餐', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(20)]);
    $o = rfOrder([], null, $user);

    $r = rfDo($o, 30, '违规', end: true);
    expect($r['message'])->toContain('已立即结束该用户当前套餐');
    expect($user->fresh()->class_expire->isFuture())->toBeFalse();
});

it('还没发货的订单，退款时不去动权益', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(20)]);
    $o = rfOrder(['status' => 'queued', 'delivered_at' => null,
        'activate_at' => now()->addDays(20)], null, $user);

    $r = rfDo($o, 30, '取消', end: true);   // 即使勾了也不该动
    expect($r['message'])->toContain('尚未发货');
    expect($user->fresh()->class_expire->isFuture())->toBeTrue();
});

it('释放优惠券的一次用量 —— 条件与占用时对称', function () {
    $c = Coupon::create(['code' => 'RF1', 'type' => 'percent', 'value' => 10,
        'max_use' => 5, 'used' => 3, 'enabled' => true]);
    $o = rfOrder(['coupon_id' => $c->id]);

    $r = rfDo($o);
    expect($c->fresh()->used)->toBe(2)->and($r['message'])->toContain('释放优惠券');
});

it('库存不自动加回，并说清楚为什么', function () {
    // `[!!]` 占用条件是"结算那一刻 stock > 0",那一刻的值现在无从得知 ——
    // 当时若是 0 根本没扣过,现在加回去就是凭空多出一件。
    $plan = rfPlan(['stock' => 7]);
    $o = rfOrder([], $plan);

    $r = rfDo($o);
    expect($plan->fresh()->stock)->toBe(7)
        ->and($r['message'])->toContain('库存未自动加回');
});

it('退款前把做不到的事先讲清楚', function () {
    // `[!]` 人是按界面上写的去理解系统行为的。事前说明比事后解释便宜。
    $o = rfOrder([], rfPlan(['stock' => 3]));
    $texts = implode("\n", RefundService::caveats($o));

    expect($texts)->toContain('不会自动撤销已发放的权益')
        ->toContain('覆盖前的值没有留存')
        ->toContain('库存不会自动加回');
});

it('退款留审计 —— 这是动钱的操作', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $o = rfOrder();

    $this->actingAs($admin)->post("/admin/orders/{$o->id}/refund",
        ['amount' => '30', 'reason' => '用户申请'])->assertRedirect();

    expect(\DB::table('audit_logs')->where('action', 'order.refund')->count())->toBe(1);
    expect($o->fresh()->status)->toBe('refunded');
});

it('退款需要填原因 —— 不留原因的退款以后无法对账', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $o = rfOrder();

    $this->actingAs($admin)->post("/admin/orders/{$o->id}/refund", ['amount' => '30'])
        ->assertSessionHasErrors('reason');
    expect($o->fresh()->status)->toBe('paid');
});

it('订单页上，可退的订单有退款按钮，且把注意事项带在按钮上', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    rfOrder([], rfPlan(['stock' => 3]));

    $this->actingAs($admin)->get('/admin/orders')->assertOk()
        ->assertSee('退款')
        // `[!!]` 注意事项必须随按钮一起出现,而不是等人点了才说。
        ->assertSee('不会自动撤销已发放的权益', false);
});

it('已退款的订单显示退了多少，且不再给退款按钮', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $o = rfOrder();
    rfDo($o, 12.5, '部分退');

    $html = $this->actingAs($admin)->get('/admin/orders')->assertOk()->getContent();
    // `[!]` 不能数 'js-refund' 出现几次 —— 对话框的 JS 里也有一处，
    // 与有没有按钮无关。要查的是【这张订单的按钮】在不在。
    expect($html)->toContain('已退 ¥12.50')
        ->and($html)->not->toContain('data-no="'.$o->order_no.'"');
});

it('财务看得到退款，但运营改不了订单 —— 权限那一层照常生效', function () {
    $o = rfOrder();
    $ops = User::factory()->create(['is_admin' => true, 'admin_role' => 'ops']);
    $finance = User::factory()->create(['is_admin' => true, 'admin_role' => 'finance']);

    // 运营有 orders.edit，所以能退；只读审计不能
    $auditor = User::factory()->create(['is_admin' => true, 'admin_role' => 'auditor']);
    $this->actingAs($auditor)->post("/admin/orders/{$o->id}/refund",
        ['amount' => '30', 'reason' => 'x'])->assertForbidden();
    expect($o->fresh()->status)->toBe('paid');

    $this->actingAs($finance)->post("/admin/orders/{$o->id}/refund",
        ['amount' => '30', 'reason' => '用户申请'])->assertRedirect();
    expect($o->fresh()->status)->toBe('refunded');
});
