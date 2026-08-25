<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function acqAdmin2(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('captures utm from landing and stores it on registration', function () {
    // 落地页带 UTM → 存 session
    $this->get('/login?utm_source=telegram&utm_medium=social&utm_campaign=spring2026')->assertOk();

    Cache::put('email_code:lead@test.local', '123456', now()->addMinutes(5));
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'lead@test.local', 'email_code' => '123456', 'name' => 'Lead',
        'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'captcha' => '7',
    ]);

    $u = User::where('email', 'lead@test.local')->first();
    expect($u->utm_source)->toBe('telegram');
    expect($u->utm_medium)->toBe('social');
    expect($u->utm_campaign)->toBe('spring2026');
});

it('keeps first-touch utm and ignores later ones', function () {
    $this->get('/login?utm_source=google');
    $this->get('/login?utm_source=baidu');   // 第二次不覆盖

    expect(session('utm.source'))->toBe('google');
});

it('computes channel conversion quality by revenue', function () {
    $plan = Plan::create(['name' => 'VIP', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100]);
    // telegram 渠道：2 注册，1 付费 ¥30
    $t1 = User::factory()->create(['utm_source' => 'telegram']);
    User::factory()->create(['utm_source' => 'telegram']);
    Order::create(['user_id' => $t1->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month', 'paid_at' => now()]);
    // 直接渠道：1 注册，0 付费
    User::factory()->create(['utm_source' => null, 'ref_by' => null, 'reg_referer' => null]);

    $res = $this->actingAs(acqAdmin2())->get('/admin/system/acquisition');
    $res->assertOk()->assertSee('渠道转化质量')->assertSee('telegram');

    $rows = $res->viewData('channelRows');
    $tg = $rows->firstWhere('channel', 'telegram');
    expect($tg['reg'])->toBe(2);
    expect($tg['paid'])->toBe(1);
    expect($tg['rate'])->toBe(50.0);
    expect((float) $tg['revenue'])->toBe(30.0);

    // 营收降序 → telegram 排第一
    expect($rows->first()['channel'])->toBe('telegram');
    $res->assertViewHas('utmSource');
});
