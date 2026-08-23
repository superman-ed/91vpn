<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows account settings page', function () {
    $this->actingAs(User::factory()->create())->get('/user/account')
        ->assertOk()->assertSee('账号设置');
});

it('changes password with correct current password', function () {
    $user = User::factory()->create(['password' => Hash::make('oldpass123')]);
    $this->actingAs($user)->post('/user/account/password', [
        'current_password' => 'oldpass123',
        'password' => 'newpass1234',
        'password_confirmation' => 'newpass1234',
    ])->assertRedirect();
    expect(Hash::check('newpass1234', $user->fresh()->password))->toBeTrue();
});

it('rejects password change with wrong current password', function () {
    $user = User::factory()->create(['password' => Hash::make('oldpass123')]);
    $this->actingAs($user)->post('/user/account/password', [
        'current_password' => 'wrong',
        'password' => 'newpass1234',
        'password_confirmation' => 'newpass1234',
    ])->assertSessionHasErrors('current_password');
    expect(Hash::check('oldpass123', $user->fresh()->password))->toBeTrue();
});

it('updates nickname', function () {
    $user = User::factory()->create(['name' => '旧昵称']);
    $this->actingAs($user)->post('/user/account/profile', ['name' => '新昵称'])->assertRedirect();
    expect($user->fresh()->name)->toBe('新昵称');
});
