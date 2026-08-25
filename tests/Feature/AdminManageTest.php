<?php

use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('user management excludes admins', function () {
    $normal = User::factory()->create(['email' => 'customer@test.local', 'is_admin' => false]);
    $otherAdmin = User::factory()->create(['email' => 'admin2@test.local', 'is_admin' => true]);

    $this->actingAs($this->admin)->get('/admin/users')->assertOk()
        ->assertSee('customer@test.local')
        ->assertDontSee('admin2@test.local');
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
