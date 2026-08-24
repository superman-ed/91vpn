<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function epPlan(): Plan
{
    return Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => true]);
}

function epOrder(int $ageMinutes): Order
{
    $order = Order::create(['user_id' => User::factory()->create(['class' => 0, 'class_expire' => now()->subDay()])->id, 'plan_id' => epPlan()->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);
    Order::whereKey($order->id)->update(['created_at' => now()->subMinutes($ageMinutes)]);

    return $order->fresh();
}

it('cancels an overdue unpaid order when no gateway', function () {
    $order = epOrder(40);

    $this->artisan('orders:expire-pending')->assertSuccessful();

    expect($order->fresh()->status)->toBe('cancelled');
});

it('keeps a recent pending order', function () {
    $order = epOrder(5);

    $this->artisan('orders:expire-pending')->assertSuccessful();

    expect($order->fresh()->status)->toBe('pending');
});

it('delivers instead of cancelling if the gateway reports the overdue order paid', function () {
    Setting::put('epay_url', 'https://pay.example.com');
    Setting::put('epay_pid', '1001');
    Setting::put('epay_key', 'secret-key');
    Http::fake(['*/api/EasyPay/queryOrder' => Http::response(['code' => 1, 'data' => ['status' => 'success']])]);

    $order = epOrder(40);

    $this->artisan('orders:expire-pending')->assertSuccessful();

    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->user->class)->toBe(1);
});
