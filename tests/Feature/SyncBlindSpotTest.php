<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\ForwardRuleService;
use App\Services\RuleSync;

/**
 * 收口审计 · 第一项：同步绿灯能不能说明「我配的规则都在跑」。
 *
 * `[!!]` applied_hash === expected 证明的是「节点跑的就是我们发出去的那份」，
 * 【不是】「我配的规则都发出去了」。中间隔着一次编译，而编译会丢东西。
 */
function sbNode(array $over = []): Node
{
    static $i = 0;
    $i++;

    return Node::create(array_merge([
        'name' => 'R'.$i, 'server' => '198.51.100.'.$i, 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'SB'.$i,
        'role' => 'relay', 'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function sbLanding(array $over = []): Node
{
    static $i = 0;
    $i++;

    return Node::create(array_merge([
        'name' => 'L'.$i, 'server' => '203.0.113.'.$i, 'port' => 39500, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'SL'.$i,
        'role' => 'landing', 'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function sbRule(Node $relay, Node $landing): ForwardRule
{
    static $i = 0;
    $i++;
    $r = ForwardRule::create([
        'name' => 'rule'.$i, 'enabled' => true, 'listen_port' => (string) (49000 + $i),
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true, 'out_type' => 'direct',
        'target_node_set' => [$landing->id], 'target_port' => (string) $landing->port,
        'send_proxy_protocol' => 2, 'trusted_transit' => true,
    ]);

    return $r->fresh('outbounds');
}

/** 模拟节点把当前编译结果原样应用了。 */
function sbApplied(Node $relay): Node
{
    $c = app(ForwardRuleService::class)->compileForNode($relay->fresh());
    $relay->update([
        'applied_hash' => $c['config_hash'], 'fetched_hash' => $c['config_hash'],
        'sync_rules' => count($c['rules']), 'sync_reported_at' => now(),
        'sync_error' => null, 'sync_degraded' => false,
    ]);

    return $relay->fresh();
}

it('`[D]` 一条规则被静默丢掉之后，同步状态仍然是绿的', function () {
    $relay = sbNode();
    $a = sbLanding();
    $b = sbLanding();
    sbRule($relay, $a);
    sbRule($relay, $b);

    $relay = sbApplied($relay);
    $c = app(ForwardRuleService::class)->compileForNode($relay);
    expect(RuleSync::of($relay, $c['config_hash'], $c['dropped'])['state'])->toBe(RuleSync::OK);
    expect($relay->sync_rules)->toBe(2);

    // 把其中一个落地停用 → 那条规则解析不出目标 → compileRule 整条丢弃
    $b->update(['enabled' => false]);
    $relay = sbApplied($relay);      // 节点如实应用了"新的那一份"

    $c = app(ForwardRuleService::class)->compileForNode($relay);
    $s = RuleSync::of($relay, $c['config_hash'], $c['dropped']);

    // 修好之后：不再是绿灯，而且点名是哪一条没发出去。
    expect($s['state'])->toBe(RuleSync::PARTIAL)
        ->and($s['label'])->toBe('部分未下发')
        ->and($s['detail'])->toContain('压根没发出去')
        ->and($s['detail'])->toContain((string) $b->id === '' ? '' : '#');
    expect($relay->sync_rules)->toBe(1);
    expect($c['dropped'])->toHaveCount(1);
});

it('`[!!]` 少传 dropped 就退回到那个静默绿灯 —— 所以调用点必须都传', function () {
    $relay = sbNode();
    $a = sbLanding();
    $b = sbLanding();
    sbRule($relay, $a);
    sbRule($relay, $b);
    $relay = sbApplied($relay);
    $b->update(['enabled' => false]);
    $relay = sbApplied($relay);

    $c = app(ForwardRuleService::class)->compileForNode($relay);
    // 这一行【故意】不传 dropped，用来钉住"漏传就会回到盲区"这件事，
    // 免得以后有人加一个新调用点时以为它是可选的。
    expect(RuleSync::of($relay, $c['config_hash'])['state'])->toBe(RuleSync::OK);
    expect(RuleSync::of($relay, $c['config_hash'], $c['dropped'])['state'])->toBe(RuleSync::PARTIAL);
});

it('批量入口默认就把 dropped 传上 —— 列表页不该是盲的', function () {
    $relay = sbNode();
    $a = sbLanding();
    $b = sbLanding();
    sbRule($relay, $a);
    sbRule($relay, $b);
    $relay = sbApplied($relay);
    $b->update(['enabled' => false]);
    $relay = sbApplied($relay);

    $all = RuleSync::forNodes([$relay], app(ForwardRuleService::class));
    expect($all[$relay->id]['state'])->toBe(RuleSync::PARTIAL);
});

it('没有规则被丢掉时，仍然是干净的绿灯', function () {
    $relay = sbNode();
    sbRule($relay, sbLanding());
    $relay = sbApplied($relay);

    $c = app(ForwardRuleService::class)->compileForNode($relay);
    expect(RuleSync::of($relay, $c['config_hash'], $c['dropped'])['state'])->toBe(RuleSync::OK);
    expect($c['dropped'])->toBe([]);
});
