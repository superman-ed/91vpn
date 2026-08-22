<?php

use App\Models\User;

it('redirects guests from admin to login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('forbids normal users from admin', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows admin users into admin dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('管理后台');
});
