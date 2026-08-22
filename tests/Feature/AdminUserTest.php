<?php

use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('lists users with search', function () {
    User::factory()->create(['email' => 'findme@test.local']);
    $this->actingAs($this->admin)->get('/admin/users?q=findme')->assertOk()->assertSee('findme@test.local');
});

it('edits user quota class and expiry', function () {
    $user = User::factory()->create(['class' => 0, 'transfer_enable' => 0]);
    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
        'class' => 3,
        'transfer_enable_gb' => 500,
        'class_expire' => now()->addDays(30)->format('Y-m-d'),
        'node_speed_limit' => 300,
        'node_ip_limit' => 9,
        'money' => 20,
    ])->assertRedirect('/admin/users');

    $user->refresh();
    expect($user->class)->toBe(3);
    expect($user->transfer_enable)->toBe(500 * 1024 ** 3);
    expect($user->node_ip_limit)->toBe(9);
});

it('toggles ban status', function () {
    $user = User::factory()->create(['banned' => false]);
    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/toggle-ban")->assertRedirect();
    expect($user->fresh()->banned)->toBeTrue();
    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/toggle-ban")->assertRedirect();
    expect($user->fresh()->banned)->toBeFalse();
});

it('blocks normal user from user admin', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin/users')->assertForbidden();
});
