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

it('filters ledger by type', function () {
    $u = User::factory()->create();
    BalanceLog::create(['user_id' => $u->id, 'amount' => 100, 'type' => 'recharge', 'balance_after' => 100, 'remark' => 'r']);
    BalanceLog::create(['user_id' => $u->id, 'amount' => -30, 'type' => 'consume', 'balance_after' => 70, 'remark' => 'c']);

    $res = $this->actingAs($this->admin)->get('/admin/finance?type=recharge')->assertOk();
    expect($res->viewData('logs')->total())->toBe(1);
});
