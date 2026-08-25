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

it('moves a plan up by swapping sort with its neighbor', function () {
    $a = Plan::create(['name' => 'A', 'price' => 10, 'period' => 'month', 'transfer_gb' => 10, 'sort' => 1]);
    $b = Plan::create(['name' => 'B', 'price' => 10, 'period' => 'month', 'transfer_gb' => 10, 'sort' => 2]);

    $this->actingAs(cpAdmin())->post("/admin/plans/{$b->id}/move", ['dir' => 'up']);

    expect($a->fresh()->sort)->toBe(2);
    expect($b->fresh()->sort)->toBe(1);   // b 上移到 a 前
});
