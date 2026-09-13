<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\ForwardRuleService;
use App\Services\RuleCheck;

/**
 * 收口审计 · 「编译时静默丢弃」这一类的第二处。
 *
 * `[!!]` 第一处（整条规则被丢）已经修了（RuleSync 的 PARTIAL）。
 * 这里是更隐蔽的一处：一条规则有多个上游，其中一个解析不出目标 ——
 * 规则照常编译、哈希照常一致、同步照常绿，而**池子悄悄少了一个上游**。
 * 运维以为有两条冗余，实际只有一条。
 */
$GLOBALS['ppSeq'] = 0;

function ppNode(string $role, array $over = []): Node
{
    $i = ++$GLOBALS['ppSeq'];

    return Node::create(array_merge([
        'name' => strtoupper($role[0]).$i,
        'server' => ($role === 'relay' ? '198.51.100.' : '203.0.113.').$i,
        'port' => $role === 'relay' ? 0 : 39500,
        'type' => $role === 'relay' ? 'vmess' : 'vless', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'PP'.$i, 'role' => $role,
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function ppRule(Node $relay, array $landings): ForwardRule
{
    $i = ++$GLOBALS['ppSeq'];
    $r = ForwardRule::create([
        'name' => 'rule'.$i, 'enabled' => true, 'listen_port' => (string) (50000 + $i),
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    foreach ($landings as $l) {
        ForwardOutbound::create([
            'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true, 'out_type' => 'direct',
            'target_node_set' => [$l->id], 'target_port' => (string) $l->port,
            'send_proxy_protocol' => 2, 'trusted_transit' => true,
        ]);
    }

    return $r->fresh('outbounds');
}

function ppTexts(ForwardRule $r): string
{
    return collect(RuleCheck::check($r->fresh('outbounds')))->pluck('text')->implode("\n");
}

it('`[D]` 两个上游停掉一个时，规则照常下发、同步照常绿', function () {
    $relay = ppNode('relay');
    $a = ppNode('landing');
    $b = ppNode('landing');
    $rule = ppRule($relay, [$a, $b]);

    $svc = app(ForwardRuleService::class);
    expect($svc->compileForNode($relay)['rules'][0]['outbounds'])->toHaveCount(2);

    $b->update(['enabled' => false]);
    $c = $svc->compileForNode($relay->fresh());

    // 规则还在（没被整条丢），所以 dropped 是空的 —— 上一条修的那个检查看不到它
    expect($c['rules'])->toHaveCount(1)
        ->and($c['dropped'])->toBe([])
        // 而池子从 2 变成了 1
        ->and($c['rules'][0]['outbounds'])->toHaveCount(1);
});

it('relay_rule 出站不再被误判成「解析不出目标」', function () {
    // `[!!]` targetsFor() 走的是 resolveTargets，而它只认 target_node_set 与
    // target_addr，【完全不处理 relay_rule】—— 那一支在 compileOutbound 里另走。
    // 于是一条纯 relay_rule 的规则会被报成"解析不出任何拨号目标"，
    // 而它其实编译得好好的。假阳性比没有告警更坏：它会让人去改一条没问题的规则。
    $relayA = ppNode('relay');
    $relayB = ppNode('relay');
    $landing = ppNode('landing');

    $inner = ppRule($relayB, [$landing]);          // 被引用的那条
    $outer = ForwardRule::create([
        'name' => 'outer', 'enabled' => true, 'listen_port' => '50999',
        'inbound_node_set' => [$relayA->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    ForwardOutbound::create([
        'rule_id' => $outer->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'relay_rule', 'relay_rule_ref' => $inner->id, 'trusted_transit' => true,
    ]);
    $outer = $outer->fresh('outbounds');

    // 它确实编译得出来
    $c = app(ForwardRuleService::class)->compileForNode($relayA);
    expect($c['rules'])->toHaveCount(1)
        ->and($c['rules'][0]['outbounds'])->not->toBeEmpty();

    // 修好之后：不再被误报
    expect(ppTexts($outer))->not->toContain('解析不出任何拨号目标');
});

it('池子少了一个上游时要报出来 —— 以为有冗余而实际没有', function () {
    $relay = ppNode('relay');
    $a = ppNode('landing');
    $b = ppNode('landing');
    $rule = ppRule($relay, [$a, $b]);
    $b->update(['enabled' => false]);

    $t = ppTexts($rule);
    expect($t)->toContain('部分上游')
        ->toContain('池子比你配的小')
        // 要点名是哪一个
        ->toContain("#{$b->id}")
        // 但不能说成"整条规则不下发"——它照常下发
        ->not->toContain('解析不出任何拨号目标');
});

it('全部上游都解析不出时，仍然报「整条跳过」而不是「池子小了」', function () {
    $relay = ppNode('relay');
    $a = ppNode('landing');
    $rule = ppRule($relay, [$a]);
    $a->update(['enabled' => false]);

    $t = ppTexts($rule);
    expect($t)->toContain('解析不出任何拨号目标')
        ->toContain('连它的入站一起')
        ->not->toContain('池子比你配的小');
});

it('引用的规则被停用时，原因要指向那条规则而不是"没选落地"', function () {
    // `[!]` relay_rule 的原因取决于【另一条规则】的状态。
    // 说成"没选落地节点"会把人引到错误的地方。
    $relayA = ppNode('relay');
    $relayB = ppNode('relay');
    $landing = ppNode('landing');
    $inner = ppRule($relayB, [$landing]);

    $outer = ForwardRule::create([
        'name' => 'outer2', 'enabled' => true, 'listen_port' => '50998',
        'inbound_node_set' => [$relayA->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    ForwardOutbound::create([
        'rule_id' => $outer->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'relay_rule', 'relay_rule_ref' => $inner->id, 'trusted_transit' => true,
    ]);
    $inner->update(['enabled' => false]);

    expect(ppTexts($outer->fresh('outbounds')))
        ->toContain("引用的规则 #{$inner->id}")
        ->toContain('已停用');
});
