<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\PromoChannel;
use App\Models\User;

// `[!!]` L-06 已确认:后台「开通」按钮【会在生产后台上被用来测试】。
//   它建的单 amount=0,所以营收不受影响 —— 但 count(distinct users) 会把
//   只拿过赠送的人算成【付费用户】,而这正是用来判断"哪个广告渠道有效"的数字。
//   首页早就排除了(L-06 的收尾),推广页漏了。

function psAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'password' => 'a12345678']);
}

function psPlan(): Plan
{
    return Plan::create(['name' => 'PS', 'price' => 50, 'period' => 'month',
        'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 0, 'ip_limit' => 0,
        'duration_days' => 30, 'sort' => 0, 'on_sale' => true, 'stock' => -1]);
}

function psOrder(User $u, Plan $p, string $no, ?string $method, float $amount): Order
{
    return Order::create([
        'user_id' => $u->id, 'plan_id' => $p->id, 'amount' => $amount,
        'status' => 'paid', 'period' => 'month', 'order_no' => $no,
        'pay_method' => $method, 'paid_at' => now(), 'delivered_at' => now(),
    ]);
}

/** 直接取控制器算出来的 stats —— 断言渲染后的 HTML 子串没有区分力(实测空真过一次)。 */
function psStats(object $test, string $code): array
{
    return $test->actingAs(psAdmin())->get('/admin/promo')->assertOk()->viewData('stats')[$code];
}

it('只拿过后台开通的用户，不算该渠道的付费用户', function () {
    PromoChannel::create(['code' => 'ADS1', 'name' => '广告一', 'note' => '', 'enabled' => true, 'pv' => 10, 'uv' => 10]);
    $comped = User::factory()->create(['promo_code' => 'ADS1', 'password' => 'x12345678']);
    psOrder($comped, psPlan(), 'PS-ADMIN', 'admin', 0);

    $s = psStats($this, 'ADS1');

    expect($s['reg'])->toBe(1);                       // 注册算他
    expect($s['paid'])->toBe(0, '只拿过赠送却被算成付费用户');
    expect((float) $s['rate'])->toBe(0.0, '付费转化率被算成了非零');
    expect($s['revenue'])->toBe(0.0);
});

it('真实购买仍然计入', function () {
    PromoChannel::create(['code' => 'ADS2', 'name' => '广告二', 'note' => '', 'enabled' => true, 'pv' => 10, 'uv' => 10]);
    $buyer = User::factory()->create(['promo_code' => 'ADS2', 'password' => 'x12345678']);
    psOrder($buyer, psPlan(), 'PS-REAL', 'balance', 50);

    $s = psStats($this, 'ADS2');

    expect($s['paid'])->toBe(1);
    expect($s['revenue'])->toBe(50.0);
});

// `[!!]` SQL 里 NULL != 'admin' 求值为 NULL 而不是 true。写成
//   where('pay_method','!=','admin') 会把历史遗留的空 pay_method 一起排除,
//   数字反而变小 —— L-06 特意记过这个坑。
it('pay_method 为空的历史订单仍然计入', function () {
    PromoChannel::create(['code' => 'ADS3', 'name' => '广告三', 'note' => '', 'enabled' => true, 'pv' => 10, 'uv' => 10]);
    $old = User::factory()->create(['promo_code' => 'ADS3', 'password' => 'x12345678']);
    psOrder($old, psPlan(), 'PS-NULL', null, 60);

    $s = psStats($this, 'ADS3');

    expect($s['paid'])->toBe(1, 'pay_method 为空的订单被误伤排除了');
    expect($s['revenue'])->toBe(60.0);
});
