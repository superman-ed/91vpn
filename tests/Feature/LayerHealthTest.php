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

/**
 * `[!!]` 状态行必须挂在一条【开了健康检查】的规则上。
 * 没开的话节点侧探测器不装配，alive 恒为真 —— 那是"没人检查过"不是"活着"，
 * 面板按无证据处理。这一条单独有用例锁着。
 */
function lhRule(bool $hc = true): \App\Models\ForwardRule
{
    static $seq = 0;

    return \App\Models\ForwardRule::create([
        'name' => 'r'.(++$seq), 'enabled' => true, 'listen_port' => (string) (40000 + $seq),
        'inbound_node_set' => [], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => $hc,
    ]);
}

function lhHop(Node $relay, bool $alive, $reportedAt = 'now', string $dial = '9.9.9.9:443', ?int $ruleId = null): RuleOutboundStatus
{
    return RuleOutboundStatus::create([
        'rule_id' => $ruleId ?? lhRule()->id, 'node_id' => $relay->id, 'tag' => 'fwd-out-1-'.fake()->numberBetween(0, 999),
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

it('这一跳可达但明显变慢时是 warn，不是 ok 也不是 bad', function () {
    // `[!!]` 与 alive 是两件事：每一项检查都绿，而每条用户连接都在多付时间。
    // 这正是 dest 那边已经踩过一次的盲区，中转→落地这一跳此前完全没有。
    $relay = lhRelay();
    $hop = lhHop($relay, true, 'now', '179.253.249.78:39500');
    $hop->update(['delay_ms' => 60, 'slow' => true]);

    $l = app(LayerHealth::class)->forNode($relay->fresh());
    expect($l['landing']['state'])->toBe('warn')
        ->and($l['landing']['detail'])->toContain('60ms')
        ->and($l['landing']['detail'])->toContain('基线')
        ->and(app(LayerHealth::class)->worst($l))->toBe('warn');
});

it('不通优先于变慢 —— 两者同时出现时报不通', function () {
    $relay = lhRelay();
    lhHop($relay, true, 'now', '1.1.1.1:443')->update(['delay_ms' => 60, 'slow' => true]);
    lhHop($relay, false, 'now', '2.2.2.2:443');

    expect(app(LayerHealth::class)->forNode($relay->fresh())['landing']['state'])->toBe('bad');
});

it('正常时把最慢的一跳报出来，省得要去别处查', function () {
    $relay = lhRelay();
    lhHop($relay, true, 'now', '1.1.1.1:443')->update(['delay_ms' => 12]);
    lhHop($relay, true, 'now', '2.2.2.2:443')->update(['delay_ms' => 47]);

    $l = app(LayerHealth::class)->forNode($relay->fresh());
    expect($l['landing']['state'])->toBe('ok')
        ->and($l['landing']['detail'])->toContain('47ms');
});

/**
 * `[!!]` 规则没开健康检查时，节点侧的探测器【根本不装配】，
 * 而 alive 走的是选路层的 dead 表 —— 那张表只有探测器会写。
 * 没有探测器 → 永远没人写 → alive 恒为 true。
 * 所以这种行里的 alive=Y 意思是【没人检查过】，不是"活着"。
 *
 * 2026-09-13 在真实数据上确认：规则 #1 hc_enabled=N，它的状态行一直报
 * alive=Y，而面板把它渲染成了绿灯 —— 一个持续存在、无人察觉的假绿灯。
 */
it('规则没开健康检查时，alive=true 不算证据', function () {
    $relay = lhRelay();
    lhHop($relay, true, 'now', '9.9.9.9:443', lhRule(hc: false)->id);

    $l = app(LayerHealth::class)->forNode($relay->fresh());
    expect($l['landing']['state'])->toBe('unknown')
        ->and($l['landing']['detail'])->toContain('没开健康检查')
        // 要给出下一步动作，否则人看到 unknown 也不知道该做什么。
        ->and($l['landing']['detail'])->toContain('打开健康检查');
});

it('开了健康检查的那条规则仍然照常判定 —— 两者混在一起时不互相污染', function () {
    $relay = lhRelay();
    lhHop($relay, true, 'now', '1.1.1.1:443', lhRule(hc: false)->id);  // 无证据
    lhHop($relay, false, 'now', '2.2.2.2:443', lhRule(hc: true)->id);  // 有证据:不通

    expect(app(LayerHealth::class)->forNode($relay->fresh())['landing']['state'])->toBe('bad');
});
