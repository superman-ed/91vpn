<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\EpayService;

function configureEpay(): void
{
    Setting::put('epay_url', 'https://pay.example.com');
    Setting::put('epay_pid', '1001');
    Setting::put('epay_key', 'secret-key');
}

function gwPlan(): Plan
{
    return Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
}

function gwOrder(User $user, Plan $plan): Order
{
    return Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => $plan->price, 'status' => 'pending', 'period' => $plan->period]);
}

it('signs and verifies params round-trip', function () {
    configureEpay();
    $epay = app(EpayService::class);
    $params = ['pid' => '1001', 'type' => 'alipay', 'out_trade_no' => '5', 'money' => '30.00', 'name' => 'VIP①'];

    $params['sign'] = $epay->sign($params);
    expect($epay->verify($params))->toBeTrue();

    $params['money'] = '0.01';   // 篡改金额
    expect($epay->verify($params))->toBeFalse();
});

it('excludes sign_type from the signature', function () {
    configureEpay();
    $epay = app(EpayService::class);
    $params = ['pid' => '1001', 'out_trade_no' => '9', 'money' => '30.00'];

    // 加不加 sign_type，签名结果应一致（sign_type 不参与）
    expect($epay->sign($params))->toBe($epay->sign($params + ['sign_type' => 'MD5']));
});

it('redirects to the gateway when configured', function () {
    configureEpay();
    $user = User::factory()->create();
    $order = gwOrder($user, gwPlan());

    $res = $this->actingAs($user)->post("/user/order/{$order->id}/pay", ['method' => 'alipay']);
    $res->assertStatus(303);   // Turbo 302→303
    expect($res->headers->get('Location'))->toContain('pay.example.com/submit.php')
        ->toContain('sign=')->toContain('out_trade_no='.$order->order_no);
    expect($order->fresh()->status)->toBe('pending');   // 未发货，等回调
});

it('maps channels to gateway type and sends usdt to the cashier', function () {
    configureEpay();
    $order = gwOrder(User::factory()->create(), gwPlan());
    $epay = app(EpayService::class);

    expect($epay->payUrl($order, 'alipay'))->toContain('&type=alipay');
    expect($epay->payUrl($order, 'wechat'))->toContain('&type=wxpay');
    expect($epay->payUrl($order, 'usdt'))->not->toContain('&type=');   // 该网关无USDT → 走收银台
});

it('falls back to mock immediate delivery when gateway not configured', function () {
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()]);
    $order = gwOrder($user, gwPlan());

    $this->actingAs($user)->post("/user/order/{$order->id}/pay", ['method' => 'alipay'])->assertRedirect('/user');
    expect($order->fresh()->status)->toBe('paid');
});

it('settles the order on a valid TRADE_SUCCESS notify', function () {
    configureEpay();
    $user = User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()]);
    $order = gwOrder($user, gwPlan());
    $epay = app(EpayService::class);

    $params = ['pid' => '1001', 'trade_no' => 'GW123', 'out_trade_no' => $order->order_no, 'type' => 'alipay', 'name' => 'VIP①', 'money' => '30.00', 'trade_status' => 'TRADE_SUCCESS'];
    $params['sign'] = $epay->sign($params);

    $this->post('/pay/epay/notify', $params)->assertOk()->assertSee('success');
    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->pay_method)->toBe('alipay');
    expect($user->fresh()->class)->toBe(1);
});

it('rejects a notify with a bad signature', function () {
    configureEpay();
    $user = User::factory()->create();
    $order = gwOrder($user, gwPlan());

    $params = ['pid' => '1001', 'out_trade_no' => $order->order_no, 'type' => 'alipay', 'money' => '30.00', 'trade_status' => 'TRADE_SUCCESS', 'sign' => 'deadbeef'];

    $this->post('/pay/epay/notify', $params)->assertOk()->assertSee('fail');
    expect($order->fresh()->status)->toBe('pending');
});

it('rejects a notify whose amount was tampered', function () {
    configureEpay();
    $user = User::factory()->create();
    $order = gwOrder($user, gwPlan());
    $epay = app(EpayService::class);

    $params = ['pid' => '1001', 'out_trade_no' => $order->order_no, 'type' => 'alipay', 'money' => '0.01', 'trade_status' => 'TRADE_SUCCESS'];
    $params['sign'] = $epay->sign($params);   // 签名合法但金额不符订单

    $this->post('/pay/epay/notify', $params)->assertOk()->assertSee('fail');
    expect($order->fresh()->status)->toBe('pending');
});
