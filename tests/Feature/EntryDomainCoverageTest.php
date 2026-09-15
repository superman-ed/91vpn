<?php

use App\Models\EntryDomain;
use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\SubscriptionService;

/**
 * 入口域名要覆盖【所有】对外发的入口，不能只覆盖经中转那一条。
 *
 * 入口域名池的意义是：IP 被墙时只改一条 DNS 记录，客户端无感。
 * 没覆盖到的那条路径，被墙时只能改节点 + 重发订阅 + 等客户端更新
 * （默认 24 小时）—— 而用户在这 24 小时里是断的。
 *
 * `[!!]` 缺口最初就出在这里：`entryHost()` 只在「经中转」那个 push 上被调用，
 * 「直连落地」那个 push 仍然发裸 IP。表结构与 `entryHost()` 都不限制节点角色，
 * 是调用点漏了一处，不是设计不支持。
 */
$GLOBALS['ed'] = 0;

function edNode(string $role, array $over = []): Node
{
    $i = ++$GLOBALS['ed'];

    return Node::create(array_merge([
        'name' => $role.$i, 'server' => '203.0.113.'.$i, 'port' => 39500,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'ED'.$i, 'role' => $role,
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function edHosts(Node $landing): array
{
    return array_column(app(SubscriptionService::class)->entrypoints($landing), 'server');
}

it('直连落地配了入口域名后，订阅发的是域名而不是裸 IP', function () {
    $landing = edNode('landing');
    EntryDomain::create([
        'domain' => 'entry-a.example.com', 'node_id' => $landing->id,
        'status' => 'active', 'pointed_ip' => $landing->server,
    ]);

    $hosts = edHosts($landing->fresh());
    expect($hosts)->toContain('entry-a.example.com');
    expect($hosts)->not->toContain($landing->server);
});

it('经中转的入口同样发域名（这条本来就对，做回归守卫）', function () {
    $landing = edNode('landing', ['accept_proxy_protocol' => true]);   // 不发直连条目
    $relay = edNode('relay', ['port' => 0]);
    EntryDomain::create([
        'domain' => 'entry-relay.example.com', 'node_id' => $relay->id,
        'status' => 'active', 'pointed_ip' => $relay->server,
    ]);
    $rule = ForwardRule::create([
        'name' => 'r', 'enabled' => true, 'listen_port' => '45001',
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    ForwardOutbound::create([
        'rule_id' => $rule->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'trusted_transit' => true,
        'target_node_set' => [$landing->id],
    ]);

    $hosts = edHosts($landing->fresh());
    expect($hosts)->toContain('entry-relay.example.com');
    expect($hosts)->not->toContain($relay->server);
});

it('没配域名时回退发真实 IP —— 向后兼容不能破', function () {
    $landing = edNode('landing');
    expect(edHosts($landing->fresh()))->toContain($landing->server);
});

it('standby / blocked 的域名不发 —— 只有 active 那个才对外', function () {
    $landing = edNode('landing');
    foreach (['standby', 'blocked'] as $st) {
        EntryDomain::create([
            'domain' => "$st.example.com", 'node_id' => $landing->id, 'status' => $st,
        ]);
    }

    $hosts = edHosts($landing->fresh());
    expect($hosts)->toContain($landing->server);          // 回退到 IP
    expect($hosts)->not->toContain('standby.example.com');
    expect($hosts)->not->toContain('blocked.example.com');
});

it('订阅生成的完整链路里也是域名（不只是 entrypoints 这一层）', function () {
    $landing = edNode('landing');
    EntryDomain::create([
        'domain' => 'entry-full.example.com', 'node_id' => $landing->id,
        'status' => 'active', 'pointed_ip' => $landing->server,
    ]);

    $yaml = app(SubscriptionService::class)->generateClash(apiUser()->fresh());
    expect($yaml)->toContain('entry-full.example.com');
    // `[!]` 反向断言:裸 IP 不该再出现,否则等于域名只是多加了一条、没替换
    expect($yaml)->not->toContain($landing->server);
});

it('入口域名只给客户端，中转拨落地仍用真实 server —— 这两个刻意不同', function () {
    $landing = edNode('landing');
    $relay = edNode('relay', ['port' => 0]);
    EntryDomain::create([
        'domain' => 'client-facing.example.com', 'node_id' => $landing->id,
        'status' => 'active', 'pointed_ip' => $landing->server,
    ]);
    $rule = ForwardRule::create([
        'name' => 'r', 'enabled' => true, 'listen_port' => '45002',
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    ForwardOutbound::create([
        'rule_id' => $rule->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'trusted_transit' => true,
        'target_node_set' => [$landing->id],
    ]);

    // 客户端侧：发门牌
    expect(edHosts($landing->fresh()))->toContain('client-facing.example.com');

    // 下发给中转的配置：拨的是【真实地址】，不是门牌
    $compiled = app(\App\Services\ForwardRuleService::class)->compileForNode($relay->fresh());
    $dials = [];
    array_walk_recursive($compiled['rules'], function ($v, $k) use (&$dials) {
        if ($k === 'dial') { $dials[] = $v; }
    });
    expect($dials)->toBe([$landing->server.':'.$landing->port]);
    // `[!!]` 若这里变成了门牌,说明有人"顺手统一"了两者 ——
    // 后果:中转要多解析一次 DNS,且健康态按 server 匹配 dial 会全部对不上,
    // 而那是静默的(hopKnownDead 匹配不到就当"未知",一律保留入口)。
    expect($dials)->not->toContain('client-facing.example.com:'.$landing->port);
});

// ─────────────────────────────────────────────────────────────────
// D-4：入口域名必须与面板域名分属不同的可注册域
// ─────────────────────────────────────────────────────────────────
function edReady(): array
{
    foreach (app(\App\Services\ServiceReadiness::class)->check() as $c) {
        if ($c['title'] === '入口域名') {
            return $c;
        }
    }
    throw new RuntimeException('上线自检里没有「入口域名」这一项');
}

it('入口域名与面板同域时报红 —— 主域被封会一起死', function () {
    config(['app.url' => 'https://app.91app.shop']);
    $n = edNode('relay');
    EntryDomain::create([
        'domain' => 'entry.91app.shop', 'node_id' => $n->id, 'status' => 'active',
    ]);

    $r = edReady();
    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('91app.shop');
    expect($r['detail'])->toContain('进不了后台');
});

it('换成单独注册的域名就转绿', function () {
    config(['app.url' => 'https://app.91app.shop']);
    $n = edNode('relay');
    EntryDomain::create([
        'domain' => 'entry.some-other-domain.com', 'node_id' => $n->id, 'status' => 'active',
    ]);

    expect(edReady()['level'])->toBe('ok');
});

it('子域深一层也要认得出同域 —— 判据是可注册域不是完整主机名', function () {
    config(['app.url' => 'https://app.91app.shop']);
    $n = edNode('relay');
    EntryDomain::create([
        'domain' => 'a.b.c.91app.shop', 'node_id' => $n->id, 'status' => 'active',
    ]);

    // `[!!]` 只比完整主机名的话,a.b.c.91app.shop ≠ app.91app.shop 会被判成"不同域",
    // 而它们其实同生共死。必须比【最后两段】。
    expect(edReady()['level'])->toBe('bad');
});

it('一个都没配时是 warn —— 说清后果但不拦路', function () {
    config(['app.url' => 'https://app.91app.shop']);

    $r = edReady();
    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('24 小时');
});

it('standby 的同域域名不报警 —— 只有 active 的才对外', function () {
    config(['app.url' => 'https://app.91app.shop']);
    $n = edNode('relay');
    EntryDomain::create([
        'domain' => 'entry.91app.shop', 'node_id' => $n->id, 'status' => 'standby',
    ]);

    expect(edReady()['level'])->toBe('warn');   // 等同于"没有启用中的"
});
