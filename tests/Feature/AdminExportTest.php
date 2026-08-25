<?php

use App\Models\BalanceLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

function expAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function csvBody($res): string
{
    ob_start();
    $res->sendContent();

    return ob_get_clean();
}

it('exports orders as CSV with BOM and filtered rows', function () {
    $u = User::factory()->create(['email' => 'buyer@test.local']);
    $plan = Plan::create(['name' => 'VIP月付', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100]);
    Order::create(['user_id' => $u->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month', 'paid_at' => now()]);
    Order::create(['user_id' => $u->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'cancelled', 'period' => 'month']);

    $res = $this->actingAs(expAdmin())->get('/admin/orders/export?status=paid');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');

    $body = csvBody($res->baseResponse);
    expect($body)->toStartWith("\xEF\xBB\xBF");        // UTF-8 BOM
    expect($body)->toContain('buyer@test.local');
    expect($body)->toContain('VIP月付');
    expect($body)->toContain('已支付');
    expect($body)->not->toContain('已取消');            // status=paid 过滤掉取消单
});

it('exports finance balance logs as CSV', function () {
    $u = User::factory()->create(['email' => 'wallet@test.local']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => 100, 'type' => 'recharge', 'balance_after' => 100, 'remark' => '在线充值']);

    $body = csvBody($this->actingAs(expAdmin())->get('/admin/finance/export')->baseResponse);
    expect($body)->toContain('wallet@test.local');
    expect($body)->toContain('充值');
    expect($body)->toContain('在线充值');
});

it('exports users as CSV honoring status filter', function () {
    User::factory()->create(['email' => 'vip@test.local', 'class' => 1, 'class_expire' => now()->addYear(), 'banned' => false]);
    User::factory()->create(['email' => 'gone@test.local', 'banned' => true]);

    $body = csvBody($this->actingAs(expAdmin())->get('/admin/users/export?status=member')->baseResponse);
    expect($body)->toContain('vip@test.local');
    expect($body)->not->toContain('gone@test.local');
});

it('records an audit entry on export', function () {
    $this->actingAs(expAdmin())->get('/admin/users/export');
    expect(\App\Models\AuditLog::where('action', 'user.export')->exists())->toBeTrue();
});
