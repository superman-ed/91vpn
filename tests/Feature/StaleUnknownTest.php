<?php

use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\RuleOutboundStatus;
use App\Services\ForwardRuleService;
use App\Services\LayerHealth;
use App\Services\RuleSync;
use Illuminate\Support\Facades\Schema;

/**
 * 收口审计 · 「UNKNOWN 被误当成 OK / BAD」这一类。
 *
 * `[!!]` 规则只有一条，但此前在五个地方各写了一遍、没有任何机械保证：
 *
 *     一台停机的节点，它【最后一次】上报的值会永远留在库里。
 *     不判过期就等于用一份陈旧快照声称一件不再为真的事。
 *     陈旧一律归 unknown —— 不是 ok（假绿灯），也不是 bad（假故障）。
 *
 * 这一组做两件事：
 *   1. 行为上逐个验：每个上报字段，新鲜时给确定结论、陈旧时给 unknown；
 *   2. 结构上兜底：nodes 表里【任何】上报字段都必须落在某个已注册的组里 ——
 *      以后新增一个 reported_* 而忘了处理过期，这里就会红。
 */

/** 上报字段分组：一组共用一个时间戳。 */
function suGroups(): array
{
    return [
        'dest' => [
            'cols' => ['reported_dest', 'reported_dest_up', 'reported_dest_failures',
                'reported_dest_latency_ms', 'reported_dest_degraded'],
            'at' => 'dest_reported_at',
        ],
        'accept_proxy' => [
            'cols' => ['reported_accept_proxy'],
            'at' => 'accept_proxy_reported_at',
        ],
        'sync' => [
            'cols' => ['applied_hash', 'fetched_hash', 'sync_error', 'sync_degraded', 'sync_rules'],
            'at' => 'sync_reported_at',
        ],
        // `[!]` 一次性诊断记录，不是持续健康信号：陈旧的扫描结果仍是
        // "那一刻的事实"。但展示时必须带上【什么时候扫的】，
        // 否则会被当成现状 —— 节点表单里确实带了 diffForHumans()。
        'dest_scan' => [
            'cols' => ['dest_scan_candidates', 'dest_scan_id', 'dest_scan_result'],
            'at' => 'dest_scan_at',
        ],
        // `[!]` 【配置】事实，不是存活信号 —— 与 dest_scan 同类：节点离线时它最后
        // 报的协议依然成立（它回来还是会跑那个）。所以刻意不按 unknown 处理。
        // 但同样受那条约束:展示时必须带上【什么时候报的】，否则会被当成现状 ——
        // 节点列表的徽章 title 里带了 diffForHumans()。
        // 存活由"在线/离线"那一列表达，不在这里重复。
        'server_type' => [
            'cols' => ['reported_server_type'],
            'at' => 'server_type_reported_at',
        ],
        // 心跳自己就是时间戳
        'heartbeat' => ['cols' => ['last_heartbeat'], 'at' => 'last_heartbeat'],
    ];
}

function suNode(array $over = []): Node
{
    static $i = 0;
    $i++;

    return Node::create(array_merge([
        'name' => 'SU'.$i, 'server' => '203.0.113.'.$i, 'port' => 39500,
        'type' => 'vless', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'SU'.$i, 'role' => 'landing', 'enabled' => true,
        'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

it('`[!!]` nodes 表里每一个上报字段都登记在册 —— 新增而忘了处理过期就会红', function () {
    $registered = collect(suGroups())->flatMap(fn ($g) => array_merge($g['cols'], [$g['at']]))->all();
    $unregistered = [];
    foreach (Schema::getColumnListing('nodes') as $col) {
        $looksReported = str_starts_with($col, 'reported_')
            || str_starts_with($col, 'sync_')
            || str_ends_with($col, '_hash')
            || in_array($col, ['last_heartbeat', 'dest_scan_result', 'dest_scan_at',
                'dest_scan_id', 'dest_scan_candidates', 'accept_proxy_reported_at',
                'dest_reported_at'], true);
        if ($looksReported && ! in_array($col, $registered, true)) {
            $unregistered[] = $col;
        }
    }

    // toBe 的第二参数【是】自定义消息（与 toContain 不同）—— 这里可以用。
    expect($unregistered)->toBe([],
        '这些字段来自节点上报，但没登记在 suGroups() 里 —— 先想清楚它过期时该显示什么');
});

it('每一组上报字段都配了时间戳列', function () {
    // 没有时间戳就没法判过期，那这个字段注定会在某天变成谎话。
    // `[!!]` 不用 toContain($x, $msg)：Pest 的 toContain 是【变参】的，
    // 第二个参数是另一个待查元素，不是消息 —— 断言会去数组里找那句中文。
    // 这个坑本次会话里已经记成 ROUND 判据 74，而我刚才又踩了一次。
    $cols = Schema::getColumnListing('nodes');
    $missing = [];
    foreach (suGroups() as $name => $g) {
        if (! in_array($g['at'], $cols, true)) {
            $missing[] = "组「{$name}」的时间戳列 {$g['at']} 不存在";
        }
        foreach ($g['cols'] as $c) {
            if (! in_array($c, $cols, true)) {
                $missing[] = "组「{$name}」登记了不存在的列 {$c}";
            }
        }
    }
    expect($missing)->toBe([]);
});

// ── 行为：新鲜给结论，陈旧给 unknown ──

it('dest：新鲜时给结论，陈旧时是 unknown 而不是 ok', function () {
    $n = suNode([
        'reality_private_key' => 'k', 'reality_dest' => 'x.example:443',
        'reported_dest' => 'x.example:443', 'reported_dest_up' => true,
        'dest_reported_at' => now(),
    ]);
    expect($n->destHealth())->toBe('ok');

    $n->update(['dest_reported_at' => now()->subHours(3)]);
    // `[!!]` 一台停机的节点，它最后一次报的 true 会永远留在库里。
    expect($n->fresh()->destHealth())->toBe('unknown');
});

it('dest 不可达时陈旧也归 unknown —— 不是留着一个假故障', function () {
    // 假故障同样有害：它会让人去查一个可能早就好了的东西。
    $n = suNode([
        'reality_private_key' => 'k', 'reality_dest' => 'x.example:443',
        'reported_dest_up' => false, 'reported_dest_failures' => 9,
        'dest_reported_at' => now()->subHours(3),
    ]);
    expect($n->destHealth())->toBe('unknown');
});

it('到落地那一跳：陈旧时 unknown', function () {
    $relay = suNode(['role' => 'relay', 'port' => 0]);
    $rule = ForwardRule::create([
        'name' => 'r', 'enabled' => true, 'listen_port' => '51001',
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    $s = RuleOutboundStatus::create([
        'rule_id' => $rule->id, 'node_id' => $relay->id, 'tag' => 'o1',
        'dial' => '9.9.9.9:443', 'backup' => false, 'alive' => true, 'live' => 0,
        'reported_at' => now(),
    ]);
    expect($relay->fresh()->relayHopHealth())->toBe('ok');

    $s->update(['reported_at' => now()->subHours(3)]);
    expect($relay->fresh()->relayHopHealth())->toBe('unknown');
});

it('PROXY 头姿态：没报过时是未知，不是"两边一致"', function () {
    $n = suNode(['accept_proxy_protocol' => true, 'reported_accept_proxy' => null]);
    expect($n->acceptProxyPosture()['reported'])->toBeNull();
});

it('同步状态：陈旧时说「状态过期」，不沿用最后一次的结论', function () {
    $relay = suNode(['role' => 'relay', 'port' => 0]);
    $c = app(ForwardRuleService::class)->compileForNode($relay);
    $relay->update([
        'applied_hash' => $c['config_hash'], 'fetched_hash' => $c['config_hash'],
        'sync_rules' => 0, 'sync_reported_at' => now()->subHours(3),
    ]);

    $s = RuleSync::of($relay->fresh(), $c['config_hash'], $c['dropped']);
    expect($s['state'])->toBe(RuleSync::STALE)
        ->and($s['detail'])->toContain('不代表现在');
});

it('分层健康态：三层里凡是拿不到证据的都报 unknown', function () {
    // 心跳新鲜但什么都没报过的中转：中转层 ok，其余 unknown/na。
    $relay = suNode(['role' => 'relay', 'port' => 0]);
    $l = app(LayerHealth::class)->forNode($relay);

    expect($l['relay']['state'])->toBe('ok')
        ->and($l['landing']['state'])->toBe('unknown')
        // 不跑 REALITY → na（"不适用"与"不知道"是两件事，不能混）
        ->and($l['dest']['state'])->toBe('na');
});

it('`[!]` na 与 unknown 不是一回事 —— 混了就会去查一个不存在的东西', function () {
    $landing = suNode();                       // 落地，不是中转
    $l = app(LayerHealth::class)->forNode($landing);

    // "本节点不是中转" = na（问题不适用），不是 unknown（该知道而不知道）
    expect($l['landing']['state'])->toBe('na')
        ->and($l['landing']['detail'])->toContain('不是中转');
});
