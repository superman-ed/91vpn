<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

/**
 * 后台「开通」不算成交单（`/admin/users` 每行那个绿色按钮）。
 *
 * `[!]` 这个按钮目前的用途是 owner 自己在生产后台上做测试。
 * 它建的是一条 amount=0 / pay_method=admin 的订单 ——
 * 金额是 0 所以"累计收入"本来就对，受影响的是【单数】。
 * 而首页那张卡片写的是「已支付订单」，这些单子一分钱没付。
 */
function agPlan(): Plan
{
    static $i = 0;
    $i++;

    return Plan::create([
        'name' => 'AG-'.$i, 'price' => 30, 'period' => 'month', 'transfer_gb' => 100,
        'reset_type' => 'monthly', 'class' => 3, 'speed_limit' => 0, 'ip_limit' => 0,
        'duration_days' => 30, 'sort' => 0, 'on_sale' => true, 'stock' => -1,
        'is_data_pack' => false,
    ]);
}

function agDash(object $t): string
{
    return $t->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin')->assertOk()->getContent();
}

it('后台开通的套餐不计入「已支付订单」，但真实购买计入', function () {
    $plan = agPlan();
    $buyer = User::factory()->create(['money' => 100]);

    // 真实购买：下单 + 余额付款
    $this->actingAs($buyer);
    $this->post('/user/order/create', ['plan_id' => $plan->id])->assertRedirect();
    $o = Order::where('user_id', $buyer->id)->latest('id')->firstOrFail();
    $this->post("/user/order/{$o->id}/pay-balance")->assertRedirect();
    expect($o->fresh()->status)->toBe('paid');

    $before = agDash($this);
    expect($before)->toContain('已支付订单');

    // 后台给另一个用户「开通」同一个套餐
    $target = User::factory()->create(['class' => 0]);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $this->post("/admin/users/{$target->id}/grant", ['plan_id' => $plan->id])->assertRedirect();

    // 前置条件：订单确实建出来了，只是不该被算进去
    expect(Order::where('pay_method', 'admin')->count())->toBe(1);
    expect((int) $target->fresh()->class)->toBe(3);

    // 单数不变
    $after = agDash($this);
    preg_match_all('/<div class="n">([\d,]+)<\/div><div class="t">已支付订单/', $before.$after, $m);
    expect($m[1])->toHaveCount(2);
    expect($m[1][0])->toBe($m[1][1]);
});

it('pay_method 为空的历史订单仍然计入 —— 别被 SQL 的 NULL 语义误伤', function () {
    $user = User::factory()->create();
    Order::create([
        'user_id' => $user->id, 'plan_id' => agPlan()->id, 'amount' => 50,
        'status' => 'paid', 'period' => 'month', 'order_no' => 'AG-NULL',
        'pay_method' => null, 'paid_at' => now(), 'delivered_at' => now(),
    ]);

    // `[!!]` 写成 where('pay_method', '!=', 'admin') 的话，
    // SQL 里 NULL != 'admin' 求值为 NULL(不是 true)，这条会被一起排除掉，
    // 单数反而变小 —— 排除噪声的改动把真实数据也删了。
    $html = agDash($this);
    preg_match('/<div class="n">([\d,]+)<\/div><div class="t">已支付订单/', $html, $m);
    expect((int) str_replace(',', '', $m[1] ?? '0'))->toBe(1);
});

it('开通仍然照常写审计和订单 —— 排除的只是统计口径', function () {
    $target = User::factory()->create(['class' => 0]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->post("/admin/users/{$target->id}/grant", ['plan_id' => agPlan()->id])->assertRedirect();

    expect(\App\Models\AuditLog::where('action', 'user.grant')->count())->toBe(1);
    expect(Order::where('pay_method', 'admin')->where('status', 'paid')->count())->toBe(1);
});
