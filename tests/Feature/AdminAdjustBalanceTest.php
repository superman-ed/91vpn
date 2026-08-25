<?php

use App\Models\BalanceLog;
use App\Models\User;

function admin(): User
{
    return User::factory()->create(['is_admin' => true, 'email' => 'op@test.local']);
}

it('records an adjust log when admin changes user balance', function () {
    $user = User::factory()->create(['money' => 10, 'transfer_enable' => 0, 'base_transfer_enable' => 0]);

    $this->actingAs(admin())->put("/admin/users/{$user->id}", [
        'class' => 1, 'transfer_enable_gb' => 5, 'money' => 88.5,
    ])->assertRedirect('/admin/users');

    expect((float) $user->fresh()->money)->toBe(88.5);
    $log = BalanceLog::where('user_id', $user->id)->where('type', 'adjust')->first();
    expect($log)->not->toBeNull();
    expect((float) $log->amount)->toBe(78.5);        // 88.5 - 10
    expect((float) $log->balance_after)->toBe(88.5);
    expect($log->remark)->toContain('op@test.local');
});

it('writes no adjust log when balance is unchanged', function () {
    $user = User::factory()->create(['money' => 20, 'transfer_enable' => 0, 'base_transfer_enable' => 0]);

    $this->actingAs(admin())->put("/admin/users/{$user->id}", [
        'class' => 1, 'transfer_enable_gb' => 5, 'money' => 20,
    ])->assertRedirect('/admin/users');

    expect(BalanceLog::where('user_id', $user->id)->where('type', 'adjust')->count())->toBe(0);
});
