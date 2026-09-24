<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * U-1 mock-pay 白嫖漏洞 —— 已修:加独立开关 `MOCK_PAY_ENABLED`(config app.mock_pay_enabled),
 * 默认【关】。守卫 = 环境 local/testing 且 开关开;生产恒拒。
 *
 * `[!!]` Pest 里 app()->environment() 恒为 'testing',守卫同时放行 local/testing,
 * 所以下面【显式把环境设成 local】并用 production 对照,测的才是生产那一个条件;
 * 开关则用 config() 显式设,不依赖 env。
 */
function u1Fixture(): array
{
    $user = User::factory()->create([
        'is_admin' => false, 'admin_role' => null,
        'class' => 0, 'class_expire' => now()->subDay(),
        'transfer_enable' => 0, 'u' => 123456, 'd' => 654321,
    ]);
    $plan = Plan::create([
        'name' => 'U1 年付', 'price' => 199, 'period' => 'year',
        'transfer_gb' => 500, 'reset_type' => 'monthly', 'class' => 5,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 365,
        'sort' => 0, 'on_sale' => true, 'stock' => -1,   // -1 = 不限量(与生产一致)
    ]);
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_no' => 'U1TEST',
        'amount' => 199, 'period' => 'year', 'status' => 'pending',
    ]);

    return [$user, $plan, $order];
}

it('开关关闭时(默认),即使 local 也拒绝 mock-pay —— 白嫖被堵', function () {
    [$user, , $order] = u1Fixture();
    $this->app->detectEnvironment(fn () => 'local');
    config(['app.mock_pay_enabled' => false]);   // 默认态

    $r = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)->post("/user/order/{$order->id}/mock-pay");

    expect($r->status())->toBe(404)
        ->and($order->fresh()->status)->toBe('pending');   // 订单没被发货
});

it('开关开启 + local 时,mock-pay 才生效(仅供本地开发)', function () {
    [$user, $plan, $order] = u1Fixture();
    $this->app->detectEnvironment(fn () => 'local');
    config(['app.mock_pay_enabled' => true]);

    $r = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)->post("/user/order/{$order->id}/mock-pay");

    expect($r->status())->toBe(303)   // Turbo 302→303
        ->and($order->fresh()->status)->toBe('paid')
        ->and($user->fresh()->class)->toBe($plan->class);
});

it('production 恒拒 —— 即使开关误开', function () {
    [$user, , $order] = u1Fixture();
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.mock_pay_enabled' => true]);   // 就算开了,生产也不行

    $r = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)->post("/user/order/{$order->id}/mock-pay");

    expect($r->status())->toBe(404)
        ->and($order->fresh()->status)->toBe('pending');
});

it('别人的订单动不了 —— 归属校验(在开关开的前提下)', function () {
    [, , $order] = u1Fixture();
    $other = User::factory()->create(['is_admin' => false]);
    $this->app->detectEnvironment(fn () => 'local');
    config(['app.mock_pay_enabled' => true]);

    $r = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($other)->post("/user/order/{$order->id}/mock-pay");

    expect($r->status())->toBe(403)
        ->and($order->fresh()->status)->toBe('pending');
});
