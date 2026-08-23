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

it('groups same-named plans into one card with 1/3/6/12-month options', function () {
    shopPlan(['name' => 'VIP①', 'period' => 'month', 'price' => 30]);
    shopPlan(['name' => 'VIP①', 'period' => 'quarter', 'price' => 85]);
    shopPlan(['name' => 'VIP①', 'period' => 'half_year', 'price' => 160]);
    shopPlan(['name' => 'VIP①', 'period' => 'year', 'price' => 300]);

    $res = $this->actingAs(User::factory()->create())->get('/user/shop')->assertOk();
    // 四个时长按钮都在
    $res->assertSee('1月')->assertSee('3月')->assertSee('6月')->assertSee('12月');
    // 只归成一张 VIP① 卡（卡头出现一次）
    expect(substr_count($res->getContent(), 'color:#6777ef">VIP①'))->toBe(1);
    // 默认展示月付价格
    $res->assertSee('>30<', false);
});

it('only groups plans that share the exact name', function () {
    shopPlan(['name' => 'VIP①', 'period' => 'month']);
    shopPlan(['name' => 'VIP②', 'period' => 'month']);

    $res = $this->actingAs(User::factory()->create())->get('/user/shop')->assertOk();
    expect(substr_count($res->getContent(), 'color:#6777ef">VIP'))->toBe(2);   // 两张不同卡
});

it('shows remaining stock for limited plans', function () {
    shopPlan(['stock' => 5]);
    $this->actingAs(User::factory()->create())->get('/user/shop')
        ->assertOk()->assertSee('限量剩余')->assertSee('>5</span>', false);
});
