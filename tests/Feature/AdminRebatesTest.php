<?php

use App\Models\Payback;
use App\Models\User;

function adminUser(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('lists site-wide rebates with totals', function () {
    $inviter = User::factory()->create(['email' => 'boss@test.local']);
    $d1 = User::factory()->create(['email' => 'down1@test.local']);
    $d2 = User::factory()->create(['email' => 'down2@test.local']);
    Payback::create(['user_id' => $inviter->id, 'from_user_id' => $d1->id, 'amount' => 2.5]);
    Payback::create(['user_id' => $inviter->id, 'from_user_id' => $d2->id, 'amount' => 5]);

    $res = $this->actingAs(adminUser())->get('/admin/rebates');
    $res->assertOk()
        ->assertSee('boss@test.local')
        ->assertSee('down1@test.local')
        ->assertSee('7.50');   // 累计
});

it('filters rebates by beneficiary or downline email', function () {
    $a = User::factory()->create(['email' => 'alice@test.local']);
    $b = User::factory()->create(['email' => 'bob@test.local']);
    $x = User::factory()->create(['email' => 'x@test.local']);
    $y = User::factory()->create(['email' => 'y@test.local']);
    Payback::create(['user_id' => $a->id, 'from_user_id' => $x->id, 'amount' => 3]);
    Payback::create(['user_id' => $b->id, 'from_user_id' => $y->id, 'amount' => 9]);

    // 命中受益人
    $this->actingAs(adminUser())->get('/admin/rebates?q=alice')
        ->assertSee('alice@test.local')->assertDontSee('bob@test.local');
    // 命中下线
    $this->actingAs(adminUser())->get('/admin/rebates?q=y@test.local')
        ->assertSee('bob@test.local')->assertDontSee('alice@test.local');
});

it('real recharge rebate shows up on the admin page', function () {
    \App\Models\Setting::put('rebate_rate', '2.5');
    $inviter = User::factory()->create(['email' => 'up@test.local', 'money' => 0]);
    $downline = User::factory()->create(['ref_by' => $inviter->id]);

    app(\App\Services\BillingService::class)->applyRecharge($downline, 100);

    expect(Payback::where('user_id', $inviter->id)->sum('amount'))->toEqual('2.50');
    $this->actingAs(adminUser())->get('/admin/rebates')
        ->assertSee('up@test.local')->assertSee('2.50');
});
