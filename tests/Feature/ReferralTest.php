<?php

use App\Models\Order;
use App\Models\Payback;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Facades\Cache;

it('generates a ref_code on registration', function () {
    Cache::put('email_code:ref@test.local', '123456', now()->addMinutes(5));
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'ref@test.local', 'email_code' => '123456', 'name' => 'r',
        'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'captcha' => '7',
    ]);
    $user = User::where('email', 'ref@test.local')->first();
    expect($user->ref_code)->not->toBeEmpty();
});

it('registers with a permanent ref_code and binds inviter', function () {
    $inviter = User::factory()->create(['ref_code' => 'REFPERM01']);
    Cache::put('email_code:d@test.local', '123456', now()->addMinutes(5));

    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'd@test.local', 'email_code' => '123456', 'name' => 'd',
        'invite_code' => 'REFPERM01',
        'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'captcha' => '7',
    ])->assertRedirect('/user');

    expect(User::where('email', 'd@test.local')->first()->ref_by)->toBe($inviter->id);
});

it('shows the invite page with ref link and downline', function () {
    $inviter = User::factory()->create(['ref_code' => 'MYCODE01']);
    User::factory()->create(['ref_by' => $inviter->id, 'email' => 'downline@test.local']);

    $this->actingAs($inviter)->get('/user/invite')
        ->assertOk()
        ->assertSee('MYCODE01')
        ->assertSee('downline@test.local');
});

it('credits 2.5% rebate to inviter when downline recharges', function () {
    $inviter = User::factory()->create(['money' => 0]);
    $downline = User::factory()->create(['ref_by' => $inviter->id, 'money' => 0]);

    $this->actingAs($downline)->post('/user/wallet/recharge', ['amount' => 100])->assertRedirect();

    // 默认充值返利 2.5% → 100 * 0.025 = 2.5
    expect((float) $inviter->fresh()->money)->toBe(2.5);
    expect(Payback::where('user_id', $inviter->id)->first()->amount)->toEqual('2.50');
});

it('does not rebate on purchase (rebate is recharge-based)', function () {
    $inviter = User::factory()->create(['money' => 0]);
    $downline = User::factory()->create(['ref_by' => $inviter->id, 'class' => 0, 'class_expire' => now()->subDay()]);
    $plan = Plan::create(['name' => 'VIP②', 'price' => 50, 'period' => 'month', 'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200, 'ip_limit' => 7, 'duration_days' => 30]);
    $order = Order::create(['user_id' => $downline->id, 'plan_id' => $plan->id, 'amount' => 50, 'status' => 'pending', 'period' => 'month']);

    app(BillingService::class)->completeOrder($order, 'mock');

    expect((float) $inviter->fresh()->money)->toBe(0.0);
    expect(Payback::where('user_id', $inviter->id)->count())->toBe(0);
});

it('gives the invited user a signup bonus', function () {
    $inviter = User::factory()->create(['ref_code' => 'BONUS001']);
    Cache::put('email_code:newbie@test.local', '123456', now()->addMinutes(5));

    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'newbie@test.local', 'email_code' => '123456', 'name' => 'newbie',
        'invite_code' => 'BONUS001',
        'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'captcha' => '7',
    ])->assertRedirect('/user');

    expect((float) User::where('email', 'newbie@test.local')->first()->money)->toBe(1.0);   // 默认注册奖励 1 元
});
