<?php

use App\Models\User;

it('shows subscription link on node settings', function () {
    $user = User::factory()->create(['invite_token' => 'TOKEN123']);
    $this->actingAs($user)->get('/user/node')
        ->assertOk()
        ->assertSee('/sub/TOKEN123');
});

it('resets subscription token', function () {
    $user = User::factory()->create(['invite_token' => 'OLDTOKEN']);
    $this->actingAs($user)->post('/user/node/reset-sub')->assertRedirect();
    expect($user->fresh()->invite_token)->not->toBe('OLDTOKEN');
});

it('resets connection password and changes uuid', function () {
    $user = User::factory()->create();
    $oldPasswd = $user->passwd;
    $oldUuid = $user->uuid;

    $this->actingAs($user)->post('/user/node/reset-passwd')->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->passwd)->not->toBe($oldPasswd);
    expect($fresh->uuid)->not->toBe($oldUuid);
});
