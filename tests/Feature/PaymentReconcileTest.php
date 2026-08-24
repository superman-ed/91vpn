<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function rcSetup(): array
{
    Setting::put('epay_url', 'https://pay.example.com');
    Setting::put('epay_pid', '1001');
    Setting::put('epay_key', 'secret-key');

    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()]);
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);
    Order::whereKey($order->id)->update(['created_at' => now()->subMinutes(10)]);   // 过对账窗口

    return [$user, $order->fresh()];
}

it('settles a pending order the gateway reports as paid', function () {
    Http::fake(['*/api/EasyPay/queryOrder' => Http::response(['code' => 1, 'msg' => '查询成功', 'data' => ['status' => 'success']])]);
    [$user, $order] = rcSetup();

    $this->artisan('payment:reconcile')->assertSuccessful();

    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->pay_method)->toBe('epay');
    expect($user->fresh()->class)->toBe(1);
});

it('leaves a pending order the gateway does not report as paid', function () {
    Http::fake(['*/api/EasyPay/queryOrder' => Http::response(['code' => 0, 'msg' => '订单不存在', 'data' => null])]);
    [$user, $order] = rcSetup();

    $this->artisan('payment:reconcile')->assertSuccessful();

    expect($order->fresh()->status)->toBe('pending');
    expect($user->fresh()->class)->toBe(0);
});

it('does not reconcile orders still inside the notify grace window', function () {
    Http::fake(['*/api/EasyPay/queryOrder' => Http::response(['code' => 1, 'data' => ['status' => 'success']])]);
    Setting::put('epay_url', 'https://pay.example.com');
    Setting::put('epay_pid', '1001');
    Setting::put('epay_key', 'secret-key');
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);   // 刚创建，在 2 分钟窗口内

    $this->artisan('payment:reconcile')->assertSuccessful();

    expect($order->fresh()->status)->toBe('pending');   // 窗口内不查
    Http::assertNothingSent();
});

it('is a no-op when the gateway is not configured', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);
    Order::whereKey($order->id)->update(['created_at' => now()->subMinutes(10)]);

    $this->artisan('payment:reconcile')->assertSuccessful();

    expect($order->fresh()->status)->toBe('pending');
});
