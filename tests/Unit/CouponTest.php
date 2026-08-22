<?php

use App\Models\Coupon;

it('applies percent discount', function () {
    $c = new Coupon(['type' => 'percent', 'value' => 20]);
    expect($c->apply(100))->toBe(80.0);
});

it('applies fixed amount discount', function () {
    $c = new Coupon(['type' => 'amount', 'value' => 15]);
    expect($c->apply(50))->toBe(35.0);
});

it('never goes below zero', function () {
    $c = new Coupon(['type' => 'amount', 'value' => 100]);
    expect($c->apply(30))->toBe(0.0);
});

it('is unusable when disabled or expired or exhausted', function () {
    expect((new Coupon(['enabled' => false, 'value' => 10, 'max_use' => -1]))->isUsable())->toBeFalse();
    expect((new Coupon(['enabled' => true, 'value' => 10, 'max_use' => -1, 'expires_at' => now()->subDay()]))->isUsable())->toBeFalse();
    expect((new Coupon(['enabled' => true, 'value' => 10, 'max_use' => 5, 'used' => 5]))->isUsable())->toBeFalse();
    expect((new Coupon(['enabled' => true, 'value' => 10, 'max_use' => -1]))->isUsable())->toBeTrue();
});
