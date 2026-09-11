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

// ── 发 PROXY 头的裸端口转发会关掉 UDP ──────────────────────────────────
//
// `[!!]` [D] sogacore lab/udp-through-relay-probe.sh 实测：freedom 出站的
// proxyProtocol 对 UDP 也生效，且把 PROXY 头当成【一个独立的数据报】先发出去,
// 载荷在下一个包里 —— 接收端整条 UDP 流错位一个包。落地的 acceptProxyProtocol
// 是 TCP sockopt，剥不掉，两端"正确配对"也一样脏。
// 节点因此主动关掉 UDP；面板要在【保存时】就说出来，否则运维只会在
// "某个 UDP 服务不通"时才发现，而那时他会先去查应用、网络和防火墙。

it('裸端口转发发 PROXY 头时,提示 UDP 会被关掉', function () {
    landingWith(reported: true);
    $texts = pairingTexts(ruleSending(2), 'warn');

    expect(implode('', $texts))->toContain('UDP 关掉')->toContain('错位');
});

it('不发 PROXY 头时不提示', function () {
    landingWith(reported: false, expected: false);

    expect(implode('', pairingTexts(ruleSending(0), 'warn')))->not->toContain('UDP 关掉');
});

// `[!]` 解协议的入站(vmess/vless/…)里 UDP 走 XUDP、封在 TCP 连接内，
// 中转只看见 TCP —— 不受影响，不该跟着报。
it('解协议的入站不提示', function () {
    landingWith(reported: true);
    $r = ruleSending(2);
    $r->update(['inbound_type' => 'vmess', 'inbound_cred' => ['uuid' => (string) Str::uuid()]]);

    expect(implode('', pairingTexts($r->fresh('outbounds'), 'warn')))->not->toContain('UDP 关掉');
});

// 停用的出站不算数。
it('停用的出站不提示', function () {
    landingWith(reported: true);
    $r = ruleSending(2);
    $r->outbounds()->update(['enabled' => false]);

    expect(implode('', pairingTexts($r->fresh('outbounds'), 'warn')))->not->toContain('UDP 关掉');
});

// ── 同一个地址上有多台落地 ──────────────────────────────────────────────
//
// `[!!]` 实测撞到过：一台机器上同时挂着生产落地(39500)与验证用的临时落地(39700),
// 而查找用的是 keyBy('server') —— 后一条覆盖前一条,校验读到的是【另一台】的
// 收头状态,报出一条与本规则无关的告警。又一个"看起来正常的错误答案"。
// 现在按 地址:端口 精确挑。

function landingAt(string $ip, int $port, ?bool $reported, bool $expected = true): Node
{
    return Node::create([
        'name' => "landing-{$port}", 'server' => $ip, 'port' => $port, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => "L{$port}",
        'role' => 'landing', 'accept_proxy_protocol' => $expected,
        'reported_accept_proxy' => $reported,
        'accept_proxy_reported_at' => $reported === null ? now()->subHour() : now(),
    ]);
}

it('同地址多台落地时,按目标端口挑对那一台', function () {
    // 39500 这台配对正常;39700 那台上报过期(按未知处理)
    landingAt('9.9.9.9', 39500, reported: true);
    landingAt('9.9.9.9', 39700, reported: null);

    $r = ruleSending(2, '9.9.9.9');
    $r->outbounds()->update(['target_port' => '39500']);

    // 挑对了 39500 那台 → 配对正常 → 不该有任何 PROXY 相关告警
    $texts = implode('', array_merge(pairingTexts($r->fresh('outbounds'), 'warn'),
                                      pairingTexts($r->fresh('outbounds'), 'broken')));
    expect($texts)->not->toContain('上报已过期')->not->toContain('没有**在收');
});

it('同地址多台落地但端口对不上任何一台时,说不知道而不是猜', function () {
    landingAt('9.9.9.9', 39500, reported: true);
    landingAt('9.9.9.9', 39700, reported: true);

    $r = ruleSending(2, '9.9.9.9');
    $r->outbounds()->update(['target_port' => '12345']);   // 谁都不是

    expect(implode('', pairingTexts($r->fresh('outbounds'), 'warn')))
        ->toContain('多台落地节点')->toContain('无法校验');
});

it('同地址只有一台时,端口对不上也按那台算', function () {
    landingAt('9.9.9.9', 39500, reported: false);   // 落地没在收头

    $r = ruleSending(2, '9.9.9.9');
    $r->outbounds()->update(['target_port' => '39999']);

    // 仍应报出"发头而落地没收"这条 broken —— 不因为端口对不上就放过
    expect(implode('', pairingTexts($r->fresh('outbounds'), 'broken')))
        ->toContain('没有**在收');
});
