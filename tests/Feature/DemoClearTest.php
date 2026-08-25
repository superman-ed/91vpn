<?php

use App\Models\AuditLog;
use App\Models\DailyStat;
use App\Models\Device;
use App\Models\EmailLog;
use App\Models\LoginLog;
use App\Models\Node;
use App\Models\NodeDailyTraffic;
use App\Models\SubscribeLog;
use App\Models\User;

it('clears all demo tables and resets user runtime fields', function () {
    $node = Node::create(['name' => 'N', 'server' => 'x', 'port' => 1, 'secret' => 's']);
    $user = User::factory()->create([
        'u' => 1000, 'd' => 2000, 'transfer_today' => 500,
        'last_used_at' => now(), 'reg_referer' => 'https://t.me/x', 'reg_ip' => '1.2.3.4',
    ]);
    DailyStat::create(['date' => today()->toDateString(), 'dau' => 5, 'peak_online' => 2, 'new_users' => 1]);
    NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->toDateString(), 'u' => 1, 'd' => 2, 'billed' => 3]);
    EmailLog::create(['to_email' => 'a@x.com', 'type' => 'code', 'subject' => 'X', 'status' => 'sent']);
    LoginLog::create(['user_id' => $user->id, 'status' => 'success', 'email' => $user->email, 'ip' => '1.1.1.1', 'logged_at' => now()]);
    SubscribeLog::create(['user_id' => $user->id, 'client' => 'Clash', 'fetched_at' => now()]);
    AuditLog::create(['admin_id' => $user->id, 'action' => 'user.update', 'description' => 'x', 'ip' => '1.1.1.1']);
    Device::create(['user_id' => $user->id, 'device_id' => 'd1', 'platform' => 'android', 'model' => 'K70', 'last_seen' => now()]);

    $this->artisan('demo:clear --force')->assertSuccessful();

    expect(DailyStat::count())->toBe(0);
    expect(NodeDailyTraffic::count())->toBe(0);
    expect(EmailLog::count())->toBe(0);
    expect(LoginLog::count())->toBe(0);
    expect(SubscribeLog::count())->toBe(0);
    expect(AuditLog::count())->toBe(0);
    expect(Device::count())->toBe(0);

    // 用户账号保留，但运行时字段重置
    $u = $user->fresh();
    expect($u)->not->toBeNull();
    expect((int) $u->u)->toBe(0);
    expect((int) $u->transfer_today)->toBe(0);
    expect($u->last_used_at)->toBeNull();
    expect($u->reg_referer)->toBeNull();
});

it('is a no-op when there is nothing to clear', function () {
    $this->artisan('demo:clear --force')
        ->expectsOutputToContain('没有可清理')
        ->assertSuccessful();
});
