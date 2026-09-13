<?php

use App\Models\Node;
use App\Models\RuleOutboundStatus;
use App\Services\LayerHealth;

/**
 * 分层健康态：把「这个节点坏了」拆成「哪一层坏了」。
 *
 * `[!!]` 这些用例锁的是 2026-09-13 实测到的那个形态：一台中转
 * online=Y、心跳 19 秒前、入站端口正常监听、面板一切正常，
 * 而它到落地那一跳【完全不通】（8 秒超时无回包）。订阅照发给用户。
 * 面板当时答得出的只有 "Node = 在线"。
 */
function lhRelay(int $hbAgo = 10): Node
{
    return Node::create([
        'name' => 'relay', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => \Illuminate\Support\Str::random(8), 'role' => 'relay',
        'last_heartbeat' => time() - $hbAgo,
    ]);
}

function lhHop(Node $relay, bool $alive, $reportedAt = 'now', string $dial = '9.9.9.9:443'): RuleOutboundStatus
{
    return RuleOutboundStatus::create([
        'rule_id' => 1, 'node_id' => $relay->id, 'tag' => 'fwd-out-1-'.fake()->numberBetween(0, 999),
        'dial' => $dial, 'backup' => false, 'alive' => $alive, 'live' => 0,
        'reported_at' => $reportedAt === 'now' ? now() : $reportedAt,
    ]);
}

it('中转活着、到落地也通时，三层各归各位', function () {
    $relay = lhRelay();
    lhHop($relay, true);

    $l = app(LayerHealth::class)->forNode($relay->fresh());
    expect($l['relay']['state'])->toBe('ok')
        ->and($l['landing']['state'])->toBe('ok')
        // 中转自己不跑 REALITY → dest 是 na，不是 ok。假绿灯会让人以为查过了。
        ->and($l['dest']['state'])->toBe('na');
});

it('心跳正常但到落地不通时，中转层绿、落地层红', function () {
    $relay = lhRelay(19);            // 就是实测里的那个 19 秒
    lhHop($relay, false, 'now', '179.253.249.78:39500');

    $l = app(LayerHealth::class)->forNode($relay->fresh());
    expect($l['relay']['state'])->toBe('ok')
        ->and($l['landing']['state'])->toBe('bad')
        ->and($l['landing']['detail'])->toContain('179.253.249.78:39500')
        // 要说清楚这一跳面板测不了 —— 否则人会去 ping 落地，然后得出错误结论。
        ->and($l['landing']['detail'])->toContain('面板测不了');

    expect(app(LayerHealth::class)->worst($l))->toBe('bad');
});

it('上报过期时落地层是 unknown 而不是 ok', function () {
    // `[!!]` 实测到的形态:中转停机 7 小时,它最后一次上报的 alive=true
    // 还原样留在库里。不判过期就等于把"中转死了"渲染成"这一跳是通的"。
    $relay = lhRelay(10);
    lhHop($relay, true, now()->subHours(7));

    $l = app(LayerHealth::class)->forNode($relay->fresh());
    expect($l['landing']['state'])->toBe('unknown')
        ->and($l['landing']['detail'])->toContain('过期');
});

it('落地节点没有"到落地"这一层，给 na 不给 ok', function () {
    $landing = Node::create([
        'name' => 'landing', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => \Illuminate\Support\Str::random(8), 'role' => 'landing',
        'last_heartbeat' => time() - 5,
    ]);

    $l = app(LayerHealth::class)->forNode($landing);
    expect($l['landing']['state'])->toBe('na')
        ->and($l['relay']['state'])->toBe('ok');
});

it('dest 劣化单独是一档：可达但每条新连接都在多付时间', function () {
    $n = Node::create([
        'name' => 'landing', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => \Illuminate\Support\Str::random(8), 'role' => 'landing',
        'last_heartbeat' => time() - 5,
        'reality_private_key' => 'k', 'reality_dest' => 'example.com:443',
        'reported_dest' => 'example.com:443', 'reported_dest_up' => true,
        'reported_dest_latency_ms' => 420, 'reported_dest_degraded' => true,
        'dest_reported_at' => now(),
    ]);

    $l = app(LayerHealth::class)->forNode($n);
    expect($l['dest']['state'])->toBe('warn')
        ->and($l['dest']['detail'])->toContain('420ms')
        ->and(app(LayerHealth::class)->worst($l))->toBe('warn');
});

it('失联的中转，中转层就是红的', function () {
    $relay = lhRelay(400);
    lhHop($relay, true);

    $l = app(LayerHealth::class)->forNode($relay->fresh());
    expect($l['relay']['state'])->toBe('bad')
        ->and($l['relay']['detail'])->toContain('失联');
});
