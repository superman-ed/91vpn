<?php

use App\Models\Node;
use App\Models\Plan;
use App\Models\User;

it('seeds 3 VIP plans', function () {
    $this->seed();
    expect(Plan::count())->toBe(3);
    expect(Plan::where('name', 'VIP①')->first()->transfer_gb)->toBe(100);
    expect(Plan::where('name', 'VIP③')->first()->class)->toBe(3);
});

it('seeds a vmess test node with unique secret', function () {
    $this->seed();
    $node = Node::where('type', 'vmess')->first();
    expect($node)->not->toBeNull();
    expect(strlen($node->secret))->toBe(32);
});

it('seeds an admin user with full tokens', function () {
    $this->seed();
    $admin = User::where('is_admin', true)->first();
    expect($admin)->not->toBeNull();
    expect($admin->email)->toBe('admin@test.local');
    expect($admin->uuid)->not->toBeEmpty();
    expect($admin->invite_token)->not->toBeEmpty();
});

it('seeders are idempotent', function () {
    $this->seed();
    $this->seed();
    expect(Plan::count())->toBe(3);
    expect(User::where('is_admin', true)->count())->toBe(1);
});
