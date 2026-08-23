<?php

use App\Models\Plan;
use App\Models\User;

function shopPlan(array $attr = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1,
        'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'period' => 'month',
        'on_sale' => true, 'stock' => -1,
    ], $attr));
}

it('shows empty state when no plans on sale', function () {
    $this->actingAs(User::factory()->create())->get('/user/shop')
        ->assertOk()->assertSee('暂无在售套餐');
});

it('shows 不限速 and 设备不限 for zero limits', function () {
    shopPlan(['speed_limit' => 0, 'ip_limit' => 0]);
    $this->actingAs(User::factory()->create())->get('/user/shop')
        ->assertOk()->assertSee('不限速')->assertSee('设备不限');
});

it('marks a sold-out plan and blocks purchase', function () {
    $plan = shopPlan(['stock' => 0]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/user/shop')->assertOk()->assertSee('已售罄');
    $this->actingAs($user)->post('/user/order/create', ['plan_id' => $plan->id])
        ->assertSessionHasErrors('plan_id');
});

it('groups plans into 1/3/6/12-month tabs by period', function () {
    shopPlan(['name' => '月付套餐', 'period' => 'month']);
    shopPlan(['name' => '季付套餐', 'period' => 'quarter']);
    shopPlan(['name' => '年付套餐', 'period' => 'year']);

    $this->actingAs(User::factory()->create())->get('/user/shop')->assertOk()
        ->assertSee('1月')->assertSee('3月')->assertSee('12月')
        ->assertSee('月付套餐')->assertSee('季付套餐')->assertSee('年付套餐')
        ->assertDontSee('6月');   // 无半年套餐则不显示该标签
});

it('shows remaining stock for limited plans', function () {
    shopPlan(['stock' => 5]);
    $this->actingAs(User::factory()->create())->get('/user/shop')
        ->assertOk()->assertSee('限量剩余 5 份');
});
