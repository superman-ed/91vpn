<?php

use App\Models\AliveIp;
use App\Models\Node;
use App\Models\User;

function onlineNode(string $name = 'HK-01'): Node
{
    return Node::create(['name' => $name, 'server' => 'cp.example.com', 'port' => 17103, 'secret' => bin2hex(random_bytes(8))]);
}

function onlineAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('lists only users reported within the online window, with their traffic', function () {
    $node = onlineNode();
    $online = User::factory()->create(['email' => 'live@test.local', 'transfer_today' => 500 * 1024 * 1024, 'u' => 1024 ** 3, 'd' => 0, 'transfer_enable' => 10 * 1024 ** 3, 'last_used_at' => now()]);
    $stale = User::factory()->create(['email' => 'gone@test.local']);

    AliveIp::create(['user_id' => $online->id, 'node_id' => $node->id, 'ip' => '1.2.3.4', 'last_seen' => now()]);
    AliveIp::create(['user_id' => $stale->id, 'node_id' => $node->id, 'ip' => '9.9.9.9', 'last_seen' => now()->subSeconds(AliveIp::ONLINE_WINDOW + 60)]);

    $res = $this->actingAs(onlineAdmin())->get('/admin/online');
    $res->assertOk()
        ->assertSee('live@test.local')
        ->assertDontSee('gone@test.local')   // 超窗口不算在线
        ->assertSee('500.00 MB')             // 今日流量
        ->assertSee('HK-01')                 // 所在节点
        ->assertSee('1.2.3.4', false);       // 在线 IP
});

it('counts distinct online users and devices', function () {
    $node = onlineNode();
    $u = User::factory()->create();
    // 同一用户两台设备
    AliveIp::create(['user_id' => $u->id, 'node_id' => $node->id, 'ip' => '1.1.1.1', 'last_seen' => now()]);
    AliveIp::create(['user_id' => $u->id, 'node_id' => $node->id, 'ip' => '2.2.2.2', 'last_seen' => now()]);

    $res = $this->actingAs(onlineAdmin())->get('/admin/online');
    $res->assertOk()
        ->assertViewHas('onlineUsers', 1)
        ->assertViewHas('onlineDevices', 2);
});

it('shows empty state when nobody is online', function () {
    $this->actingAs(onlineAdmin())->get('/admin/online')
        ->assertOk()->assertSee('当前没有在线用户');
});

it('sums traffic over the selected date range', function () {
    $node = onlineNode();
    \App\Models\NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->toDateString(), 'u' => 1024 ** 3, 'd' => 0, 'billed' => 1024 ** 3]);
    \App\Models\NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->subDays(3)->toDateString(), 'u' => 1024 ** 3, 'd' => 0, 'billed' => 1024 ** 3]);
    \App\Models\NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->subDays(20)->toDateString(), 'u' => 5 * 1024 ** 3, 'd' => 0, 'billed' => 5 * 1024 ** 3]);

    // 近 7 天：只含今天 + 3 天前 = 2GB
    $this->actingAs(onlineAdmin())->get('/admin/online?from='.today()->subDays(6)->toDateString().'&to='.today()->toDateString())
        ->assertOk()
        ->assertViewHas('rangeTraffic', 2 * 1024 ** 3)
        ->assertViewHas('rangeDays', 7)
        ->assertSee('区间总流量');

    // 近 30 天：含 20 天前的 5GB → 共 7GB
    $this->actingAs(onlineAdmin())->get('/admin/online?from='.today()->subDays(29)->toDateString().'&to='.today()->toDateString())
        ->assertOk()
        ->assertViewHas('rangeTraffic', 7 * 1024 ** 3);
});

it('range summary reflects dau and online peaks within the window', function () {
    \App\Models\DailyStat::create(['date' => today()->toDateString(), 'dau' => 30, 'peak_online' => 10, 'new_users' => 1]);
    \App\Models\DailyStat::create(['date' => today()->subDays(2)->toDateString(), 'dau' => 55, 'peak_online' => 22, 'new_users' => 2]);

    $this->actingAs(onlineAdmin())->get('/admin/online?from='.today()->subDays(6)->toDateString().'&to='.today()->toDateString())
        ->assertOk()
        ->assertViewHas('peakDau', 55)
        ->assertViewHas('peakOnline', 22);
});

it('dashboard shows current online users and today active count', function () {
    $node = onlineNode();
    $u = User::factory()->create(['last_used_at' => now()]);
    User::factory()->create(['last_used_at' => now()->subDays(2)]);   // 非今日活跃
    AliveIp::create(['user_id' => $u->id, 'node_id' => $node->id, 'ip' => '1.1.1.1', 'last_seen' => now()]);

    $this->actingAs(onlineAdmin())->get('/admin')
        ->assertOk()
        ->assertViewHas('onlineUsers', 1)
        ->assertViewHas('activeToday', 1);
});
