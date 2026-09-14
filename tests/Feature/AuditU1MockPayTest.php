<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * 审计实验 U-1 —— 只做观察，不修复。
 *
 * 待证命题：`POST /user/order/{order}/mock-pay` 在 APP_ENV=local 下，
 * 允许【任何普通登录用户】把自己的待支付订单变成已支付并拿到套餐，
 * 而生产容器实测就是 APP_ENV=local。
 *
 * `[!!]` 实验设计上必须避开一个混淆：Pest 里 app()->environment() 恒为
 * 'testing'，而守卫同时允许 'local' 与 'testing' —— 直接跑会分不清
 * "因为是测试环境"还是"因为生产是 local"。所以下面【显式把环境设成 local】，
 * 并用 production 做对照。这样测的才是生产那一个条件。
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
                // `[!!]` stock=-1 才是"不限量"（与生产一致，实测 7 个套餐全是 -1）。
        // 写 0 的话 settleOrder 会抛"已售罄" —— 上一轮就是这么被自己的
        // fixture 骗了一次：303 跳到站点根，看着像"mockPay 不生效"。
        'sort' => 0, 'on_sale' => true, 'stock' => -1,
    ]);
    $order = Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_no' => 'U1TEST',
        'amount' => 199, 'period' => 'year', 'status' => 'pending',
    ]);

    return [$user, $plan, $order];
}

it('APP_ENV=local 时，普通用户可以不付钱拿到套餐', function () {
    [$user, $plan, $order] = u1Fixture();
    $this->app->detectEnvironment(fn () => 'local');

    expect($user->class)->toBe(0)
        ->and($user->class_expire->isPast())->toBeTrue();

    // `[!!]` 只关掉 CSRF 这一个中间件。把环境强制成 local 之后，
    // Laravel 不再认为自己在跑单元测试，ValidateCsrfToken 就生效了 ——
    // 上一轮三条全是 419，请求【根本没到控制器】，那样测出来的什么都不是。
    $r = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)->post("/user/order/{$order->id}/mock-pay");

    $order = $order->fresh();
    $user = $user->fresh();

    // 观察到的实际结果
    dump([
        'HTTP' => $r->status(),
        'Location' => $r->headers->get('Location'),
        '订单状态' => $order->status,
        'paid_at' => (string) $order->paid_at,
        'delivered_at' => (string) $order->delivered_at,
        '订单金额' => (string) $order->amount,
        'pay_method' => $order->pay_method,
        '用户等级' => $user->class,
        '到期时间' => (string) $user->class_expire,
        '流量配额GB' => round($user->transfer_enable / 1024 ** 3, 1),
        '已用流量被清零' => $user->u === 0 && $user->d === 0,
    ]);

    expect($order->status)->toBe('paid')
        ->and($order->delivered_at)->not->toBeNull()
        ->and($user->class)->toBe($plan->class)
        ->and($user->class_expire->isFuture())->toBeTrue();
});

it('APP_ENV=production 时同一请求被拒 —— 证明分界线就是这一个值', function () {
    [$user, , $order] = u1Fixture();
    $this->app->detectEnvironment(fn () => 'production');

    $r = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)->post("/user/order/{$order->id}/mock-pay");
    dump(['HTTP' => $r->status(), '订单状态' => $order->fresh()->status]);

    expect($r->status())->toBe(404)
        ->and($order->fresh()->status)->toBe('pending');
});

it('别人的订单动不了 —— 归属校验独立于环境', function () {
    [$user, , $order] = u1Fixture();
    $other = User::factory()->create(['is_admin' => false]);
    $this->app->detectEnvironment(fn () => 'local');

    $r = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($other)->post("/user/order/{$order->id}/mock-pay");
    dump(['HTTP' => $r->status(), '订单状态' => $order->fresh()->status]);

    expect($r->status())->toBe(403)
        ->and($order->fresh()->status)->toBe('pending');
});
