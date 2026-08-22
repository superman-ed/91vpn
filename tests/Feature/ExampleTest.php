<?php

use App\Models\User;

it('redirects guests from / to login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('redirects authenticated users from / to dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/user');
});
