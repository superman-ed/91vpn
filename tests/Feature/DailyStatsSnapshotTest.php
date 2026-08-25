<?php

use App\Models\AliveIp;
use App\Models\DailyStat;
use App\Models\Node;
use App\Models\User;

function statNode(): Node
{
    return Node::create(['name' => 'N1', 'server' => 'cp.example.com', 'port' => 100, 'secret' => bin2hex(random_bytes(8))]);
}

it('snapshots today dau, new users and current online', function () {
    $node = statNode();
    User::factory()->create(['last_used_at' => now()]);              // 今日活跃
    User::factory()->create(['last_used_at' => now()->subDays(3)]); // 非今日
    $online = User::factory()->create(['last_used_at' => now()]);
    AliveIp::create(['user_id' => $online->id, 'node_id' => $node->id, 'ip' => '1.1.1.1', 'last_seen' => now()]);

    $this->artisan('stats:snapshot')->assertSuccessful();

    $stat = DailyStat::whereDate('date', today())->first();
    expect($stat->dau)->toBe(2);        // 两个今日活跃
    expect($stat->peak_online)->toBe(1);
    expect($stat->new_users)->toBe(3);  // 三个都是今天 create 的
});

it('accumulates peak_online as the max across runs', function () {
    $node = statNode();
    $a = User::factory()->create();
    $b = User::factory()->create();

    // 第一次：2 人在线
    AliveIp::create(['user_id' => $a->id, 'node_id' => $node->id, 'ip' => '1.1.1.1', 'last_seen' => now()]);
    AliveIp::create(['user_id' => $b->id, 'node_id' => $node->id, 'ip' => '2.2.2.2', 'last_seen' => now()]);
    $this->artisan('stats:snapshot');
    expect(DailyStat::whereDate('date', today())->first()->peak_online)->toBe(2);

    // 第二次：只剩 1 人在线，峰值不应回落
    AliveIp::truncate();
    AliveIp::create(['user_id' => $a->id, 'node_id' => $node->id, 'ip' => '1.1.1.1', 'last_seen' => now()]);
    $this->artisan('stats:snapshot');
    expect(DailyStat::whereDate('date', today())->first()->peak_online)->toBe(2);   // 仍为 2
});

it('renders the 30-day trend on the online page', function () {
    DailyStat::create(['date' => today()->subDays(1)->toDateString(), 'dau' => 42, 'peak_online' => 15, 'new_users' => 3]);
    $admin = User::factory()->create(['is_admin' => true]);

    $res = $this->actingAs($admin)->get('/admin/online');
    $res->assertOk()
        ->assertSee('近 30 日趋势')
        ->assertViewHas('trend')
        ->assertViewHas('trendMax', 42);
});
