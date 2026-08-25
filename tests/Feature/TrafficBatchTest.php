<?php

use App\Models\DailyTraffic;
use App\Models\Node;
use App\Models\NodeDailyTraffic;
use App\Models\User;
use App\Services\NodeUserService;
use App\Services\TrafficService;
use Illuminate\Support\Facades\Cache;

function batchNode(float $rate = 1.5): Node
{
    // fresh()：回填 node_class/node_group 等 DB 默认值(生产中节点由中间件从 DB 加载，字段齐全)
    return Node::create(['name' => 'B', 'server' => 'cp.example.com', 'port' => 100, 'traffic_rate' => $rate, 'secret' => bin2hex(random_bytes(8))])->fresh();
}

it('bulk-settles many users in one report with correct per-user billing', function () {
    $node = batchNode(2.0);
    $a = User::factory()->create(['u' => 0, 'd' => 0, 'transfer_today' => 0]);
    $b = User::factory()->create(['u' => 0, 'd' => 0, 'transfer_today' => 0]);

    $n = app(TrafficService::class)->record($node, [
        ['user_id' => $a->id, 'u' => 100, 'd' => 200],
        ['user_id' => $b->id, 'u' => 10, 'd' => 5],
    ]);

    expect($n)->toBe(2);
    // a：(100,200)*2 = (200,400)
    expect((int) $a->fresh()->u)->toBe(200);
    expect((int) $a->fresh()->d)->toBe(400);
    expect((int) $a->fresh()->transfer_today)->toBe(600);
    // b：(10,5)*2 = (20,10)
    expect((int) $b->fresh()->u)->toBe(20);
    expect((int) $b->fresh()->transfer_today)->toBe(30);
    expect($a->fresh()->last_used_at)->not->toBeNull();
});

it('aggregates duplicate user rows within the same batch', function () {
    $node = batchNode(1.0);
    $u = User::factory()->create(['u' => 0, 'd' => 0]);

    app(TrafficService::class)->record($node, [
        ['user_id' => $u->id, 'u' => 100, 'd' => 0],
        ['user_id' => $u->id, 'u' => 50, 'd' => 25],   // 同用户第二条
    ]);

    expect((int) $u->fresh()->u)->toBe(150);
    expect((int) $u->fresh()->d)->toBe(25);
    // daily_traffic 也应累加到一行
    $dt = DailyTraffic::where('user_id', $u->id)->whereDate('date', today())->first();
    expect((int) $dt->u)->toBe(150);
    expect((int) $dt->d)->toBe(25);
});

it('accumulates node raw and billed correctly under batch', function () {
    $node = batchNode(2.0);
    $a = User::factory()->create();
    $b = User::factory()->create();

    app(TrafficService::class)->record($node, [
        ['user_id' => $a->id, 'u' => 1000, 'd' => 3000],
        ['user_id' => $b->id, 'u' => 500, 'd' => 500],
    ]);

    $row = NodeDailyTraffic::where('node_id', $node->id)->whereDate('date', today())->first();
    expect((int) $row->u)->toBe(1500);            // 原始 1000+500
    expect((int) $row->d)->toBe(3500);            // 原始 3000+500
    expect((int) $row->billed)->toBe((1500 + 3500) * 2);  // 计费 = 原始×2
});

it('caches the servable user list for the ttl window', function () {
    Cache::flush();
    $node = batchNode();
    User::factory()->create(['class_expire' => now()->addYear(), 'transfer_enable' => 10 ** 9, 'u' => 0, 'd' => 0]);

    $first = app(NodeUserService::class)->servableUsers($node);
    expect($first)->toHaveCount(1);

    // 新增一个用户后立即再查：应命中缓存(仍是 1)，直到 TTL 过期
    User::factory()->create(['class_expire' => now()->addYear(), 'transfer_enable' => 10 ** 9, 'u' => 0, 'd' => 0]);
    $cached = app(NodeUserService::class)->servableUsers($node);
    expect($cached)->toHaveCount(1);

    // 清缓存后重查：反映最新(2)
    Cache::forget("mod_mu:users:{$node->id}");
    expect(app(NodeUserService::class)->servableUsers($node))->toHaveCount(2);
});
