<?php

use App\Models\BalanceLog;

const WALLET_AUTH = ['Authorization' => 'Bearer TESTTOKEN123'];

it('returns balance, totals and recent records', function () {
    $u = apiUser(['money' => 42.5]);
    BalanceLog::create(['user_id' => $u->id, 'amount' => 30, 'type' => 'recharge', 'balance_after' => 42.5, 'remark' => '充值']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => -30, 'type' => 'consume', 'balance_after' => 12.5, 'remark' => '购买套餐']);

    $res = $this->getJson('/api/wallet', WALLET_AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect((float) $res->json('data.balance'))->toBe(42.5);
    expect((float) $res->json('data.totals.recharge'))->toBe(30.0);
    expect((float) $res->json('data.totals.consume'))->toBe(30.0);
    expect($res->json('data.records'))->toHaveCount(2);
});

it('mock-credits a recharge in the testing env (no gateway)', function () {
    apiUser(['money' => 12.5]);
    $res = $this->postJson('/api/wallet/recharge', ['amount' => 20], WALLET_AUTH)
        ->assertOk()->assertJsonPath('ret', 1)->assertJsonPath('data.status', 'credited');
    expect((float) $res->json('data.balance'))->toBe(32.5);   // 12.5 + 20
    expect((float) App\Models\User::first()->money)->toBe(32.5);
});

it('rejects a recharge below the minimum', function () {
    apiUser();
    $this->postJson('/api/wallet/recharge', ['amount' => 0], WALLET_AUTH)->assertStatus(422);
});

it('rejects wallet access without a token', function () {
    $this->getJson('/api/wallet')->assertStatus(401);
});
