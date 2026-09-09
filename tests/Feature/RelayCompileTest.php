<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\ForwardRuleService;
use App\Services\RuleSync;

/**
 * ADR-008 P2：规则编译与同步状态判定，搬入后仍然成立。
 *
 * `[!]` 端点相关的用例留到 P3（那时下发/上报端点才存在）。这里只覆盖
 * 不依赖 HTTP 的两件：**指纹怎么算**、**同步状态怎么判**。
 */
function compileNode(): Node
{
    return Node::create([
        'name' => 'relay', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'S', 'role' => 'relay',
    ]);
}

function ruleOnNode(Node $n, string $name = 'r1', string $port = '30001'): ForwardRule
{
    $r = ForwardRule::create([
        'name' => $name, 'enabled' => true, 'listen_port' => $port,
        'inbound_node_set' => [$n->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'target_addr' => '9.9.9.9', 'target_port' => '443',
        'trusted_transit' => true,
    ]);

    return $r->fresh('outbounds');
}

it('编译出的下发带指纹', function () {
    $n = compileNode();
    ruleOnNode($n);
    $out = app(ForwardRuleService::class)->compileForNode($n);
    expect($out['config_hash'] ?? '')->toMatch('/^[a-f0-9]{16}$/');
});

// `[!!]` 规则变了指纹必须变 —— 否则面板改了、节点以为没变，
// 而"节点跑的是哪一份"这个问题就永远答错。
it('规则变了指纹就变', function () {
    $n = compileNode();
    $r = ruleOnNode($n);
    $svc = app(ForwardRuleService::class);
    $before = $svc->compileForNode($n)['config_hash'];

    $r->update(['listen_port' => '30002']);
    expect($svc->compileForNode($n->fresh())['config_hash'])->not->toBe($before);
});

// 反过来：与下发内容无关的改动【不该】让指纹漂 ——
// 否则节点会被无谓地重建内核，而重建期间是不服务的。
it('无关改动不改指纹', function () {
    $n = compileNode();
    $r = ruleOnNode($n);
    $svc = app(ForwardRuleService::class);
    $before = $svc->compileForNode($n)['config_hash'];

    $r->touch();                       // 只动 updated_at
    $n->update(['note' => '换个备注']);  // 节点上与下发无关的字段
    expect($svc->compileForNode($n->fresh())['config_hash'])->toBe($before);
});

// `[!!]` 陌生哈希（面板没见过的那一份）按【落后】处理，不能当作"同步了" ——
// 节点跑着一份我们不认识的规则，恰恰是最该显示出来的情形。
it('陌生哈希按落后处理而不是当作已同步', function () {
    $n = compileNode();
    ruleOnNode($n);
    $expected = app(ForwardRuleService::class)->compileForNode($n)['config_hash'];

    $n->update(['applied_hash' => 'deadbeefdeadbeef', 'fetched_hash' => 'deadbeefdeadbeef']);
    $st = RuleSync::of($n->fresh(), $expected);
    expect($st['state'])->not->toBe('ok');
});

it('applied 与期望一致时判为已同步', function () {
    $n = compileNode();
    ruleOnNode($n);
    $expected = app(ForwardRuleService::class)->compileForNode($n)['config_hash'];

    $n->update(['applied_hash' => $expected, 'fetched_hash' => $expected,
        'sync_reported_at' => now(), 'sync_degraded' => false]);
    expect(RuleSync::of($n->fresh(), $expected)['state'])->toBe('ok');
});
