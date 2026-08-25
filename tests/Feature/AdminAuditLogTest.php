<?php

use App\Models\AuditLog;
use App\Models\Node;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

function auditAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'email' => 'op@test.local']);
}

it('records an audit entry when admin bans a user', function () {
    $target = User::factory()->create(['email' => 'victim@test.local', 'banned' => false]);

    $this->actingAs(auditAdmin())->post("/admin/users/{$target->id}/toggle-ban");

    $log = AuditLog::where('action', 'user.ban')->first();
    expect($log)->not->toBeNull();
    expect($log->admin->email)->toBe('op@test.local');
    expect($log->target_type)->toBe('User');
    expect($log->target_id)->toBe($target->id);
    expect($log->description)->toContain('victim@test.local');
    expect($log->ip)->not->toBe('');
});

it('records an audit entry when admin marks an order paid', function () {
    $user = User::factory()->create();
    $plan = Plan::create(['name' => 'VIP', 'price' => 10, 'period' => 'month', 'transfer_gb' => 100]);
    $order = Order::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 10, 'status' => 'pending', 'period' => 'month']);

    $this->actingAs(auditAdmin())->post("/admin/orders/{$order->id}/mark-paid");

    expect(AuditLog::where('action', 'order.mark_paid')->exists())->toBeTrue();
});

it('records audit when granting admin to an existing user', function () {
    $target = User::factory()->create(['email' => 'promote@test.local', 'is_admin' => false]);

    $this->actingAs(auditAdmin())->post('/admin/admins', ['email' => 'promote@test.local']);

    expect(AuditLog::where('action', 'admin.grant')->where('target_id', $target->id)->exists())->toBeTrue();
    expect($target->fresh()->is_admin)->toBeTrue();
});

it('records audit when deleting a plan', function () {
    $plan = Plan::create(['name' => '旧套餐', 'price' => 5, 'period' => 'month', 'transfer_gb' => 50]);

    $this->actingAs(auditAdmin())->delete("/admin/plans/{$plan->id}");

    expect(AuditLog::where('action', 'plan.delete')->exists())->toBeTrue();
});

it('records audit when deleting a coupon', function () {
    $coupon = \App\Models\Coupon::create(['code' => 'SAVE10', 'type' => 'amount', 'value' => 10]);

    $this->actingAs(auditAdmin())->delete("/admin/coupons/{$coupon->id}");

    expect(AuditLog::where('action', 'coupon.delete')->exists())->toBeTrue();
});

it('lists and filters audit logs by group', function () {
    $admin = auditAdmin();
    AuditLog::create(['admin_id' => $admin->id, 'action' => 'user.update', 'description' => '更新用户 a', 'ip' => '1.1.1.1']);
    AuditLog::create(['admin_id' => $admin->id, 'action' => 'node.delete', 'description' => '删除节点 X', 'ip' => '1.1.1.1']);

    $this->actingAs($admin)->get('/admin/system/audit')
        ->assertOk()->assertSee('更新用户 a')->assertSee('删除节点 X');

    // 按分组过滤 node.*
    $this->actingAs($admin)->get('/admin/system/audit?group=node')
        ->assertOk()->assertSee('删除节点 X')->assertDontSee('更新用户 a');
});
