<?php

use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('user management includes admins (admin is also a user)', function () {
    User::factory()->create(['email' => 'customer@test.local', 'is_admin' => false]);
    User::factory()->create(['email' => 'admin2@test.local', 'is_admin' => true]);

    $this->actingAs($this->admin)->get('/admin/users')->assertOk()
        ->assertSee('customer@test.local')
        ->assertSee('admin2@test.local');   // 管理员也在用户管理里
});

it('refuses to ban an admin from user management', function () {
    $other = User::factory()->create(['is_admin' => true, 'banned' => false]);

    $this->actingAs($this->admin)->post("/admin/users/{$other->id}/toggle-ban");
    expect($other->fresh()->banned)->toBeFalse();
});

it('admin page lists only admins', function () {
    User::factory()->create(['email' => 'customer@test.local', 'is_admin' => false]);

    $this->actingAs($this->admin)->get('/admin/admins')->assertOk()
        ->assertSee($this->admin->email)
        ->assertDontSee('customer@test.local');
});

it('promotes an existing user to admin', function () {
    $u = User::factory()->create(['email' => 'promote@test.local', 'is_admin' => false]);

    $this->actingAs($this->admin)->post('/admin/admins', ['email' => 'promote@test.local'])->assertRedirect('/admin/admins');
    expect($u->fresh()->is_admin)->toBeTrue();
});

it('creates a new admin account with password', function () {
    $this->actingAs($this->admin)->post('/admin/admins', [
        'email' => 'newadmin@test.local', 'name' => 'Boss', 'password' => 'secret123',
    ])->assertRedirect('/admin/admins');

    $created = User::where('email', 'newadmin@test.local')->first();
    expect($created)->not->toBeNull();
    expect($created->is_admin)->toBeTrue();
});

it('rejects new admin account without password', function () {
    $this->actingAs($this->admin)->post('/admin/admins', ['email' => 'nopass@test.local'])
        ->assertSessionHasErrors('password');
    expect(User::where('email', 'nopass@test.local')->exists())->toBeFalse();
});

it('demotes another admin but not self or the last admin', function () {
    $other = User::factory()->create(['is_admin' => true]);

    // 撤销其他管理员 OK
    $this->actingAs($this->admin)->delete("/admin/admins/{$other->id}")->assertRedirect();
    expect($other->fresh()->is_admin)->toBeFalse();

    // 不能撤销自己
    $this->actingAs($this->admin)->delete("/admin/admins/{$this->admin->id}");
    expect($this->admin->fresh()->is_admin)->toBeTrue();
});
