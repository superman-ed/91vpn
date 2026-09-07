<?php

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\User;

function cpAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('batch-generates unique coupons with a prefix', function () {
    $this->actingAs(cpAdmin())->post('/admin/coupons/batch', [
        'count' => 25, 'prefix' => 'SPRING', 'type' => 'percent', 'value' => 15, 'max_use' => 1,
        'periods' => ['year'],
    ])->assertRedirect('/admin/coupons');

    expect(Coupon::count())->toBe(25);
    expect(Coupon::where('code', 'like', 'SPRING%')->count())->toBe(25);
    expect(Coupon::pluck('code')->unique()->count())->toBe(25);   // 全唯一
    $c = Coupon::first();
    expect($c->type)->toBe('percent');
    expect((float) $c->value)->toBe(15.0);
    expect($c->periods)->toBe(['year']);
    expect(\App\Models\AuditLog::where('action', 'coupon.create')->exists())->toBeTrue();
});

it('rejects batch count over the limit', function () {
    $this->actingAs(cpAdmin())->post('/admin/coupons/batch', ['count' => 999, 'type' => 'amount', 'value' => 5])
        ->assertSessionHasErrors('count');
});

it('toggles plan on-sale state', function () {
    $plan = Plan::create(['name' => 'VIP', 'price' => 10, 'period' => 'month', 'transfer_gb' => 50, 'on_sale' => true]);

    $this->actingAs(cpAdmin())->post("/admin/plans/{$plan->id}/toggle-sale");
    expect($plan->fresh()->on_sale)->toBeFalse();

    $this->actingAs(cpAdmin())->post("/admin/plans/{$plan->id}/toggle-sale");
    expect($plan->fresh()->on_sale)->toBeTrue();
});

// `[!!]` 断言【顺序】而不是具体的 sort 数值。
//
// 原本断言的是 a=2 / b=1，那是"交换两个 sort 值"这个旧实现的产物。
// 控制器后来改成"整表重写为 0,1,2… 连续 sort"（见 PlanController::move
// 的说明：交换法在多个套餐共用同一 sort 时会退化），于是结果变成
// b=0 / a=1，测试就一直红着 —— 而功能是好的。
//
// 断言数值等于把实现细节焊进测试。这里改成断言"b 排在 a 前面"，
// 那才是这个功能要保证的东西，换哪种实现都成立。
it('moves a plan up so it sorts before its neighbor', function () {
    $a = Plan::create(['name' => 'A', 'price' => 10, 'period' => 'month', 'transfer_gb' => 10, 'sort' => 1]);
    $b = Plan::create(['name' => 'B', 'price' => 10, 'period' => 'month', 'transfer_gb' => 10, 'sort' => 2]);

    // 前置条件：动之前 a 确实在 b 前面。少了这句，"两个都没动"也能让下面通过。
    expect($a->fresh()->sort)->toBeLessThan($b->fresh()->sort);

    $this->actingAs(cpAdmin())->post("/admin/plans/{$b->id}/move", ['dir' => 'up']);

    expect($b->fresh()->sort)->toBeLessThan($a->fresh()->sort);
    // sort 应当被重写成连续值，不留空洞 —— 这是新实现的要点。
    expect(Plan::orderBy('sort')->pluck('sort')->all())->toBe([0, 1]);
});
