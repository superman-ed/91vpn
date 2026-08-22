<?php

use Illuminate\Support\Facades\Cache;

it('sends an email code and stores it in cache', function () {
    $res = $this->postJson('/auth/send', ['email' => 'send@test.local']);
    $res->assertOk()->assertJson(['ok' => true]);
    expect(Cache::get('email_code:send@test.local'))->not->toBeNull();
});

it('validates email format on send', function () {
    $this->postJson('/auth/send', ['email' => 'not-an-email'])->assertStatus(422);
});
