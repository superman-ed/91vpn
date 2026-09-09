<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\RuleCheck;

/**
 * 「中转发 PROXY 头 ↔ 落地收 PROXY 头」的配对校验（ADR-008 合并后版本）。
 *
 * `[!!]` 这条错配的失败形态是**两端都不报错**：中转日志一切正常、落地一行
 * 都没有，只有客户端连不上（sogacore compatibility/b2-reality-through-relay.md §2.5）。
 * 面板是唯一有机会在出事之前发现它的地方。
 *
 * `[!]` 合并之后这是**本地一次查询** —— 拆分时它要跨面板走内部只读 API
 * （LandingPosture + 两侧 token + 宿主网关地址），那套已随合并删掉。
 * 但判定语义一字未改：**未知不算通过**。
 */
function landingWith(?bool $reported, bool $expected = true, $reportedAt = 'now'): Node
{
    return Node::create([
        'name' => 'landing', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'L',
        'role' => 'landing',
        'accept_proxy_protocol' => $expected,
        'reported_accept_proxy' => $reported,
        'accept_proxy_reported_at' => $reportedAt === 'now' ? now() : $reportedAt,
    ]);
}

function ruleSending(int $send, string $target = '9.9.9.9'): ForwardRule
{
    $relay = Node::create([
        'name' => 'relay', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'R', 'role' => 'relay',
    ]);
    $r = ForwardRule::create([
        'name' => 'r', 'enabled' => true, 'listen_port' => '30001',
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'target_addr' => $target, 'target_port' => '443',
        'send_proxy_protocol' => $send, 'trusted_transit' => true,
    ]);

    return $r->fresh('outbounds');
}

/** @return array<int,string> */
function pairingTexts(ForwardRule $r, string $level): array
{
    return array_column(array_filter(RuleCheck::check($r),
        fn ($x) => $x['level'] === $level), 'text');
}

it('配对正确时不报', function () {
    landingWith(reported: true);
    expect(pairingTexts(ruleSending(2), 'broken'))->toBe([]);
});

it('发头而落地没在收 → broken', function () {
    landingWith(reported: false, expected: false);
    expect(pairingTexts(ruleSending(2), 'broken'))->not->toBeEmpty();
});

// 反向：落地收头而这条不发 —— 落地会拒绝每一个不带头的连接，同样全断。
it('落地收头而出站不发头 → broken', function () {
    landingWith(reported: true);
    expect(pairingTexts(ruleSending(0), 'broken'))->not->toBeEmpty();
});

it('从未上报按未知处理，不是通过', function () {
    landingWith(reported: null);
    $r = ruleSending(0);
    expect(pairingTexts($r, 'broken'))->toBe([]);
    expect(pairingTexts($r, 'warn'))->not->toBeEmpty();
});

// `[!!]` 一台停机的落地，最后一次上报的 true 会永远留在库里 ——
// 不判过期就等于把"节点死了"渲染成"配对没问题"。
it('上报过期按未知处理', function () {
    landingWith(reported: true, reportedAt: now()->subHour());
    $r = ruleSending(0);   // 若把陈旧的 true 当真，这里会报 broken
    expect(pairingTexts($r, 'broken'))->toBe([]);
    expect(pairingTexts($r, 'warn'))->not->toBeEmpty();
});

it('地址在库里找不到时报未知', function () {
    landingWith(reported: true);           // server = 9.9.9.9
    $r = ruleSending(2, '8.8.8.8');        // 出站指向别的地址
    expect(pairingTexts($r, 'broken'))->toBe([]);
    expect(pairingTexts($r, 'warn'))->not->toBeEmpty();
});

it('面板配的与节点实际在跑的不一致时提示', function () {
    landingWith(reported: false, expected: true);
    expect(array_filter(pairingTexts(ruleSending(0), 'warn'),
        fn ($t) => str_contains($t, '还没拉到')))->not->toBeEmpty();
});

// `[!!]` 中转与落地可能是【同一台机器】（stage 5 就是这样）：同一个 server
// 地址两条节点记录。配对校验必须只认落地那条 —— 否则它查的是中转自己的
// 收头状态，给出一个看起来正常的错误答案。
it('同一地址上有中转记录时，配对校验仍认落地那条', function () {
    // 落地：没在收头
    landingWith(reported: false, expected: false);
    // 同一台机器上的中转记录（后建，keyBy 会让它覆盖）
    Node::create([
        'name' => '同机中转', 'server' => '9.9.9.9', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'R2',
        'role' => 'relay', 'accept_proxy_protocol' => true,
        'reported_accept_proxy' => true, 'accept_proxy_reported_at' => now(),
    ]);

    // 规则发头、落地没收 → 必须仍然报 broken
    expect(pairingTexts(ruleSending(2), 'broken'))->not->toBeEmpty();
});
