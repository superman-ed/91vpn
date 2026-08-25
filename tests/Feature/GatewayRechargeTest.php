<?php

use App\Models\BalanceLog;
use App\Models\Recharge;
use App\Models\User;
use App\Services\EpayService;

function cfgEpay(): void
{
    \App\Models\Setting::put('epay_url', 'https://pay.example.com');
    \App\Models\Setting::put('epay_pid', '1001');
    \App\Models\Setting::put('epay_key', 'secret-key');
}

it('mock recharge credits immediately when gateway not configured', function () {
    $user = User::factory()->create(['money' => 0]);
    $this->actingAs($user)->post('/user/wallet/recharge', ['amount' => 50])->assertRedirect();
    expect((float) $user->fresh()->money)->toBe(50.0);
    expect(Recharge::count())->toBe(0);   // 未配网关不建充值单
});

it('gateway recharge creates a pending recharge and redirects', function () {
    cfgEpay();
    $user = User::factory()->create(['money' => 0]);

    $res = $this->actingAs($user)->post('/user/wallet/recharge', ['amount' => 100]);
    $res->assertStatus(303);
    expect($res->headers->get('Location'))->toContain('pay.example.com/submit.php');

    $r = Recharge::where('user_id', $user->id)->first();
    expect($r->status)->toBe('pending');
    expect((float) $user->fresh()->money)->toBe(0.0);   // 未到账
});

it('credits balance on a valid recharge notify with trade_no', function () {
    cfgEpay();
    $user = User::factory()->create(['money' => 0]);
    $r = Recharge::create(['user_id' => $user->id, 'amount' => 100, 'status' => 'pending']);
    $epay = app(EpayService::class);

    $params = ['pid' => '1001', 'trade_no' => 'TX999', 'out_trade_no' => $r->order_no, 'money' => '100.00', 'trade_status' => 'TRADE_SUCCESS'];
    $params['sign'] = $epay->sign($params);

    $this->post('/pay/epay/notify', $params)->assertOk()->assertSee('success');

    expect($r->fresh()->status)->toBe('paid');
    expect($r->fresh()->trade_no)->toBe('TX999');
    expect((float) $user->fresh()->money)->toBe(100.0);
    $log = BalanceLog::where('type', 'recharge')->where('user_id', $user->id)->first();
    expect($log->trade_no)->toBe('TX999');
});

it('recharge notify is idempotent', function () {
    cfgEpay();
    $user = User::factory()->create(['money' => 0]);
    $r = Recharge::create(['user_id' => $user->id, 'amount' => 100, 'status' => 'pending']);
    $epay = app(EpayService::class);
    $params = ['pid' => '1001', 'trade_no' => 'TX1', 'out_trade_no' => $r->order_no, 'money' => '100.00', 'trade_status' => 'TRADE_SUCCESS'];
    $params['sign'] = $epay->sign($params);

    $this->post('/pay/epay/notify', $params);
    $this->post('/pay/epay/notify', $params);   // 重复回调

    expect((float) $user->fresh()->money)->toBe(100.0);   // 只到账一次
});
