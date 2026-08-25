<?php

use App\Models\BalanceLog;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('blocks normal users from finance page', function () {
    $u = User::factory()->create();
    $this->actingAs($u)->get('/admin/finance')->assertForbidden();
});

it('shows finance ledger with type totals', function () {
    $u = User::factory()->create(['email' => 'flow@test.local']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => 100, 'type' => 'recharge', 'balance_after' => 100, 'remark' => '充值']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => -30, 'type' => 'consume', 'balance_after' => 70, 'remark' => '购买套餐']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => 2.5, 'type' => 'rebate', 'balance_after' => 72.5, 'remark' => '邀请返利']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => 1, 'type' => 'bonus', 'balance_after' => 73.5, 'remark' => '注册奖励']);

    $res = $this->actingAs($this->admin)->get('/admin/finance')->assertOk()->assertSee('flow@test.local');
    expect((float) $res->viewData('sumRecharge'))->toBe(100.0);
    expect((float) $res->viewData('sumConsume'))->toBe(30.0);
    expect((float) $res->viewData('sumRebate'))->toBe(2.5);
    expect((float) $res->viewData('sumBonus'))->toBe(1.0);
});

it('links consume log to its order and shows order_no', function () {
    $u = User::factory()->create(['money' => 100, 'class' => 0, 'class_expire' => now()->subDay()]);
    $plan = \App\Models\Plan::create(['name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30]);
    $order = \App\Models\Order::create(['user_id' => $u->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs($u)->post("/user/order/{$order->id}/pay", ['method' => 'balance']);

    $log = BalanceLog::where('type', 'consume')->first();
    expect($log->order_id)->toBe($order->id);
    $this->actingAs($this->admin)->get('/admin/finance')->assertOk()->assertSee($order->fresh()->order_no);
});

it('filters ledger by type', function () {
    $u = User::factory()->create();
    BalanceLog::create(['user_id' => $u->id, 'amount' => 100, 'type' => 'recharge', 'balance_after' => 100, 'remark' => 'r']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => -30, 'type' => 'consume', 'balance_after' => 70, 'remark' => 'c']);

    $res = $this->actingAs($this->admin)->get('/admin/finance?type=recharge')->assertOk();
    expect($res->viewData('logs')->total())->toBe(1);
});
