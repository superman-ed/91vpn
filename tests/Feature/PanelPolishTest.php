<?php

use App\Models\AliveIp;
use App\Models\Node;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;

function polishAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

// ---- 工单:用户自助结单 / 后台重开 ----

it('lets a user close their own ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'x', 'status' => 'open', 'last_reply_at' => now()]);

    $this->actingAs($user)->post("/user/ticket/{$ticket->id}/close")->assertRedirect();
    expect($ticket->fresh()->status)->toBe('closed');
});

it('forbids closing someone else\'s ticket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $owner->id, 'subject' => 'x', 'status' => 'open', 'last_reply_at' => now()]);

    $this->actingAs($other)->post("/user/ticket/{$ticket->id}/close")->assertForbidden();
    expect($ticket->fresh()->status)->toBe('open');
});

it('lets admin reopen a closed ticket', function () {
    $ticket = Ticket::create(['user_id' => User::factory()->create()->id, 'subject' => 'x', 'status' => 'closed', 'last_reply_at' => now()]);

    $this->actingAs(polishAdmin())->post("/admin/tickets/{$ticket->id}/reopen")->assertRedirect();
    expect($ticket->fresh()->status)->toBe('open');
});

it('filters admin tickets by keyword', function () {
    $u = User::factory()->create(['email' => 'needle@test.local']);
    Ticket::create(['user_id' => $u->id, 'subject' => '找不到的主题', 'status' => 'open', 'last_reply_at' => now()]);
    Ticket::create(['user_id' => User::factory()->create()->id, 'subject' => '无关工单', 'status' => 'open', 'last_reply_at' => now()]);

    $this->actingAs(polishAdmin())->get('/admin/tickets?q=needle')
        ->assertOk()->assertSee('找不到的主题')->assertDontSee('无关工单');
});

// ---- 设置自检:未配置时给出提示,不抛异常 ----

it('settings test-email warns when SMTP unconfigured', function () {
    $this->actingAs(polishAdmin())->post('/admin/settings/test-email', ['test_email' => 'me@test.local'])
        ->assertRedirect();
    // 不抛异常即通过;未配置时只回提示
});

it('settings test-gateway warns when gateway unconfigured', function () {
    $this->actingAs(polishAdmin())->post('/admin/settings/test-gateway')->assertRedirect();
});

// ---- 用户在线设备页 ----

it('shows the online devices page with current alive ips', function () {
    $user = User::factory()->create();
    $node = Node::create(['name' => '香港01', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'x']);
    AliveIp::create(['user_id' => $user->id, 'node_id' => $node->id, 'ip' => '1.2.3.4', 'last_seen' => now()]);
    // 过期的不算在线
    AliveIp::create(['user_id' => $user->id, 'node_id' => $node->id, 'ip' => '9.9.9.9', 'last_seen' => now()->subHour()]);

    $this->actingAs($user)->get('/user/devices')
        ->assertOk()->assertSee('1.2.3.4')->assertDontSee('9.9.9.9');
});

// ---- 优惠券筛选 ----

it('filters coupons by status', function () {
    \App\Models\Coupon::create(['code' => 'ACTIVE1', 'type' => 'percent', 'value' => 10, 'max_use' => -1, 'used' => 0, 'enabled' => true]);
    \App\Models\Coupon::create(['code' => 'OFF1', 'type' => 'percent', 'value' => 10, 'max_use' => -1, 'used' => 0, 'enabled' => false]);

    $this->actingAs(polishAdmin())->get('/admin/coupons?status=disabled')
        ->assertOk()->assertSee('OFF1')->assertDontSee('ACTIVE1');
});
