<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\RuleOutboundStatus;
use App\Services\SubscriptionService;

/**
 * 订阅不再把【已知不通】的中转入口发给用户。
 *
 * `[!!]` 关键在判据的方向：是「有证据说明死了」才丢，不是「没有证据说明活着」就丢。
 * 误判的代价不对称 ——
 *   漏判：用户多一条连不上的选择，客户端的 url-test 会自己绕开（P0-1）。
 *   误判：一条其实能用的入口凭空消失，用户无感，排查却要从订阅一路回溯到上报链路。
 */
function hfLanding(): Node
{
    return Node::create([
        'name' => 'L', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => \Illuminate\Support\Str::random(8), 'role' => 'landing',
        'accept_proxy_protocol' => true,      // 不发直连条目，只留中转入口，便于断言
        'last_heartbeat' => time() - 5, 'online' => true,
    ]);
}

function hfRelay(string $ip): Node
{
    return Node::create([
        'name' => 'R-'.$ip, 'server' => $ip, 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => \Illuminate\Support\Str::random(8), 'role' => 'relay',
        'last_heartbeat' => time() - 5, 'online' => true, 'enabled' => true,
    ]);
}

function hfRule(Node $landing, array $relays): ForwardRule
{
    $r = ForwardRule::create([
        'name' => 'r', 'enabled' => true, 'listen_port' => '39600',
        'inbound_node_set' => array_map(fn (Node $n) => $n->id, $relays),
        'inbound_type' => 'direct', 'balance' => 'roundrobin',
        // `[!]` 必须开:没开的话节点侧探测器不装配、alive 恒为真,
        // 这些行按无证据处理,整组用例就测不到想测的东西了。
        'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'target_addr' => $landing->server,
        'target_port' => (string) $landing->port,
        'send_proxy_protocol' => 2, 'trusted_transit' => true,
    ]);

    return $r->fresh('outbounds');
}

function hfHop(ForwardRule $rule, Node $relay, bool $alive, $at = 'now', ?string $dial = '9.9.9.9:443'): void
{
    // `[!]` tag 要逐条不同:唯一键是 (rule_id, node_id, tag)。
    // 同一台中转对同一个落地【本来就会】有多个出站(地址池),
    // 真实上报里它们的 tag 是 fwd-out-1-0 / fwd-out-1-1。
    static $seq = 0;
    RuleOutboundStatus::create([
        'rule_id' => $rule->id, 'node_id' => $relay->id,
        'tag' => 'fwd-out-'.$rule->id.'-'.($seq++), 'dial' => $dial,
        'backup' => false, 'alive' => $alive, 'live' => 0,
        'reported_at' => $at === 'now' ? now() : $at,
    ]);
}

/** 订阅里这个落地对应的入口地址列表。 */
function hfEntries(Node $landing): array
{
    return array_column(app(SubscriptionService::class)->entrypoints($landing->fresh()), 'server');
}

it('有新鲜证据说明这一跳不通时，该入口不进订阅', function () {
    $L = hfLanding();
    [$good, $dead] = [hfRelay('1.1.1.1'), hfRelay('2.2.2.2')];
    $rule = hfRule($L, [$good, $dead]);
    hfHop($rule, $good, true);
    hfHop($rule, $dead, false);

    expect(hfEntries($L))->toBe(['1.1.1.1']);
});

it('上报已过期时保留入口 —— 未知不等于死了', function () {
    $L = hfLanding();
    $r = hfRelay('2.2.2.2');
    $rule = hfRule($L, [$r]);
    hfHop($rule, $r, false, now()->subHours(7));   // 明确说不通，但已过期

    expect(hfEntries($L))->toBe(['2.2.2.2']);
});

it('从来没上报过时保留入口 —— 刚部署的中转是最常见的无数据场景', function () {
    $L = hfLanding();
    $r = hfRelay('2.2.2.2');
    hfRule($L, [$r]);                              // 一条状态都不写

    expect(hfEntries($L))->toBe(['2.2.2.2']);
});

it('同一落地有多个上游时，只要还有一个活着就保留', function () {
    $L = hfLanding();
    $r = hfRelay('2.2.2.2');
    $rule = hfRule($L, [$r]);
    hfHop($rule, $r, false);                       // 地址池里一个死了
    hfHop($rule, $r, true);                        // 另一个还活着

    expect(hfEntries($L))->toBe(['2.2.2.2']);
});

it('dial 对不上具体落地时，退回按整条规则判', function () {
    $L = hfLanding();
    $r = hfRelay('2.2.2.2');
    $rule = hfRule($L, [$r]);
    hfHop($rule, $r, false, 'now', '5.5.5.5:443'); // dial 指向别处

    expect(hfEntries($L))->toBe([]);
});

it('中转失联时仍按原有口径丢掉 —— 这条判定没被改动', function () {
    $L = hfLanding();
    $r = hfRelay('2.2.2.2');
    $r->update(['online' => false]);
    $rule = hfRule($L, [$r]);
    hfHop($rule, $r, true);                        // 上报说通,但心跳已停

    expect(hfEntries($L))->toBe([]);
});
