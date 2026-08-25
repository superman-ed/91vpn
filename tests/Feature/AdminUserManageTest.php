<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('filters users by member status', function () {
    User::factory()->create(['email' => 'mem@test.local', 'class' => 1, 'class_expire' => now()->addDays(10)]);
    User::factory()->create(['email' => 'free@test.local', 'class' => 0, 'class_expire' => now()->subDay()]);

    $res = $this->actingAs($this->admin)->get('/admin/users?status=member')->assertOk();
    $res->assertSee('mem@test.local')->assertDontSee('free@test.local');
});

it('update syncs base_transfer_enable with quota', function () {
    $u = User::factory()->create(['transfer_enable' => 10 * 1024 ** 3, 'base_transfer_enable' => 10 * 1024 ** 3, 'class' => 1]);

    $this->actingAs($this->admin)->put("/admin/users/{$u->id}", [
        'class' => 2, 'transfer_enable_gb' => 200, 'money' => 5,
    ])->assertRedirect('/admin/users');

    $u->refresh();
    expect($u->transfer_enable)->toBe(200 * 1024 ** 3);
    expect($u->base_transfer_enable)->toBe(200 * 1024 ** 3);   // 同步
});

it('resets used traffic', function () {
    $u = User::factory()->create(['u' => 50 * 1024 ** 3, 'd' => 30 * 1024 ** 3]);

    $this->actingAs($this->admin)->post("/admin/users/{$u->id}/reset-traffic")->assertRedirect();
    expect((int) $u->fresh()->u)->toBe(0);
    expect((int) $u->fresh()->d)->toBe(0);
});

it('resets login password', function () {
    $u = User::factory()->create();

    $this->actingAs($this->admin)->post("/admin/users/{$u->id}/reset-password", ['password' => 'newpass123'])->assertRedirect();
    expect(Hash::check('newpass123', $u->fresh()->password))->toBeTrue();
});

it('cannot edit an admin from user management', function () {
    $other = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin)->get("/admin/users/{$other->id}/edit")->assertForbidden();
});
