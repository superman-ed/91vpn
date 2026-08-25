<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\PromoChannel;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function promoAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('creates a promo code and records audit', function () {
    $this->actingAs(promoAdmin())->post('/admin/promo', ['name' => '张三代理', 'code' => 'zhangsan', 'note' => 'tg @zs']);

    $c = PromoChannel::first();
    expect($c->code)->toBe('ZHANGSAN');   // 归一化大写
    expect($c->name)->toBe('张三代理');
    expect(\App\Models\AuditLog::where('action', 'promo.create')->exists())->toBeTrue();
});

it('attributes a registration to the promo code from the ?ch link', function () {
    PromoChannel::create(['code' => 'AGENT1', 'name' => '代理1', 'enabled' => true]);

    // 通过推广链接落地 → 注册
    $this->get('/login?ch=AGENT1')->assertOk();
    Cache::put('email_code:lead@test.local', '123456', now()->addMinutes(5));
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'lead@test.local', 'email_code' => '123456', 'name' => 'Lead',
        'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'captcha' => '7',
    ]);

    expect(User::where('email', 'lead@test.local')->value('promo_code'))->toBe('AGENT1');
});

it('ignores an unknown or disabled promo code', function () {
    PromoChannel::create(['code' => 'OFF', 'name' => 'x', 'enabled' => false]);
    $this->get('/login?ch=OFF');
    Cache::put('email_code:u2@test.local', '123456', now()->addMinutes(5));
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'u2@test.local', 'email_code' => '123456', 'name' => 'U2',
        'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'captcha' => '7',
    ]);

    expect(User::where('email', 'u2@test.local')->value('promo_code'))->toBeNull();
});

it('computes agent performance (reg / paid / revenue)', function () {
    $ch = PromoChannel::create(['code' => 'AG', 'name' => '代理', 'enabled' => true]);
    $plan = Plan::create(['name' => 'VIP', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100]);
    $u1 = User::factory()->create(['promo_code' => 'AG']);
    User::factory()->create(['promo_code' => 'AG']);   // 注册但没付费
    Order::create(['user_id' => $u1->id, 'plan_id' => $plan->id, 'amount' => 30, 'status' => 'paid', 'period' => 'month', 'paid_at' => now()]);

    $res = $this->actingAs(promoAdmin())->get('/admin/promo');
    $res->assertOk()->assertSee('代理');
    $s = $res->viewData('stats')['AG'];
    expect($s['reg'])->toBe(2);
    expect($s['paid'])->toBe(1);
    expect($s['rate'])->toBe(50.0);
    expect((float) $s['revenue'])->toBe(30.0);
});

it('keeps historical attribution after deleting a promo code', function () {
    $ch = PromoChannel::create(['code' => 'DEL', 'name' => 'x', 'enabled' => true]);
    User::factory()->create(['promo_code' => 'DEL']);

    $this->actingAs(promoAdmin())->delete("/admin/promo/{$ch->id}");

    expect(PromoChannel::count())->toBe(0);
    expect(User::where('promo_code', 'DEL')->count())->toBe(1);   // 用户归因保留
});
