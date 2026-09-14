<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\SubscriptionService;

/**
 * 订阅里发的必须是【客户端要连的那一跳】。
 *
 * `[!!]` 落地挂在中转后面时，客户端连的是中转 IP + 转发规则的监听端口；
 * 落地那个端口往往还被防火墙锁成只收中转(accept_proxy 姿态)。
 * 把落地地址发出去，用户会得到一个连不上且【没有任何报错】的条目 ——
 * 只看到"连不上"。
 *
 * 这个洞之前没暴露，纯粹因为测试时中转和落地是同一台机器、只差端口号。
 */
function subLanding(array $over = []): Node
{
    return Node::create(array_merge([
        'name' => '日本01', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'L',
        'role' => 'landing', 'online' => true, 'enabled' => true,
    ], $over));
}

function subRelay(string $name = '香港中转', string $ip = '1.1.1.1'): Node
{
    return Node::create([
        'name' => $name, 'server' => $ip, 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'R'.$ip,
        'role' => 'relay', 'online' => true, 'enabled' => true,
    ]);
}

function subRule(Node $relay, Node $landing, string $port = '30001'): ForwardRule
{
    $r = ForwardRule::create([
        'name' => '中转→'.$landing->name, 'enabled' => true, 'listen_port' => $port,
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true, 'out_type' => 'direct',
        'target_node_set' => [$landing->id], 'target_addr' => $landing->server,
        'target_port' => (string) $landing->port, 'trusted_transit' => true,
        'send_proxy_protocol' => 2,
    ]);

    return $r->fresh('outbounds');
}

it('没有中转时只有直连一条', function () {
    $n = subLanding();
    $eps = app(SubscriptionService::class)->entrypoints($n);

    expect($eps)->toHaveCount(1);
    expect($eps[0])->toMatchArray(['server' => '9.9.9.9', 'port' => 443, 'label' => '']);
});

it('挂在中转后面时,直连与中转各一条', function () {
    $landing = subLanding();
    $relay = subRelay();
    subRule($relay, $landing);

    $eps = app(SubscriptionService::class)->entrypoints($landing);

    expect($eps)->toHaveCount(2);
    expect(collect($eps)->pluck('server')->all())->toBe(['9.9.9.9', '1.1.1.1']);
    expect($eps[1]['port'])->toBe(30001);          // 规则的监听端口，不是落地的 443
    expect($eps[1]['label'])->toBe('香港中转');
});

// `[!!]` accept_proxy 的落地【不能】直连:那个端口上每个连接都必须带 PROXY 头,
// 直连客户端会被全部拒绝。发直连条目等于发一个必然连不上的东西。
it('accept_proxy 的落地不发直连条目', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    $relay = subRelay();
    subRule($relay, $landing);

    $eps = app(SubscriptionService::class)->entrypoints($landing);

    expect($eps)->toHaveCount(1);
    expect($eps[0]['server'])->toBe('1.1.1.1');
    expect(collect($eps)->pluck('server'))->not->toContain('9.9.9.9');
});

it('多个中转各出一条', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    subRule(subRelay('香港中转', '1.1.1.1'), $landing, '30001');
    subRule(subRelay('台湾中转', '2.2.2.2'), $landing, '30002');

    $eps = app(SubscriptionService::class)->entrypoints($landing);

    expect(collect($eps)->pluck('server')->all())->toBe(['1.1.1.1', '2.2.2.2']);
    expect(collect($eps)->pluck('port')->all())->toBe([30001, 30002]);
});

it('停用的规则与停用的中转不出条目', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    $relay = subRelay();
    subRule($relay, $landing)->update(['enabled' => false]);

    expect(app(SubscriptionService::class)->entrypoints($landing))->toBeEmpty();

    $r2 = subRule(subRelay('停用中转', '3.3.3.3'), $landing, '30003');
    Node::where('server', '3.3.3.3')->update(['enabled' => false]);

    expect(app(SubscriptionService::class)->entrypoints($landing->fresh()))->toBeEmpty();
});

// `[!!]` 与直连落地口径一致:失联(online=false,心跳停)的中转不能进订阅,
// 否则用户拿到一条指向死中转、连不上又无报错的条目。
it('失联的中转不出条目', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    $relay = subRelay();
    subRule($relay, $landing);

    $relay->update(['online' => false]);
    expect(app(SubscriptionService::class)->entrypoints($landing->fresh()))->toBeEmpty();
});

// 同一中转、同一监听端口被多条规则引用时,只发一条(按 server:port 去重)。
it('重复入口去重', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    $relay = subRelay();
    subRule($relay, $landing, '30001');
    subRule($relay, $landing, '30001');   // 第二条规则,同中转同端口 → 应被去重

    $eps = app(SubscriptionService::class)->entrypoints($landing);
    expect($eps)->toHaveCount(1);
    expect($eps[0])->toMatchArray(['server' => '1.1.1.1', 'port' => 30001]);
});

it('端口范围取第一个', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    subRule(subRelay(), $landing, '30010-30020');

    expect(app(SubscriptionService::class)->entrypoints($landing)[0]['port'])->toBe(30010);
});

// 入口域名(域名池):中转有【在用】入口域名时,订阅发域名而非裸 IP ——
// 客户端从此连域名,IP 被墙只改 A 记录、无感跟过去。
it('中转有在用入口域名时订阅发域名', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    $relay = subRelay();
    subRule($relay, $landing, '30001');
    \App\Models\EntryDomain::create([
        'domain' => 'cp.example.com', 'node_id' => $relay->id, 'status' => 'active', 'pointed_ip' => '1.1.1.1',
    ]);

    $eps = app(SubscriptionService::class)->entrypoints($landing->fresh());
    expect($eps)->toHaveCount(1);
    expect($eps[0])->toMatchArray(['server' => 'cp.example.com', 'port' => 30001]);
});

// 备用/被墙的入口域名【不替换】—— 只有 active 那个才对外发。回退发裸 IP。
it('备用入口域名不替换,仍发裸 IP', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    $relay = subRelay();
    subRule($relay, $landing, '30001');
    \App\Models\EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id, 'status' => 'standby']);

    expect(app(SubscriptionService::class)->entrypoints($landing->fresh())[0]['server'])->toBe('1.1.1.1');
});

// `[!!]` 这条是终点:落地地址绝不能出现在订阅正文里。
it('订阅正文里没有落地地址,只有中转入口', function () {
    $landing = subLanding(['accept_proxy_protocol' => true]);
    subRule(subRelay(), $landing);
    $u = apiUser();

    $clash = app(SubscriptionService::class)->generateClash($u->fresh());
    expect($clash)->toContain('1.1.1.1')->not->toContain('9.9.9.9');
    expect($clash)->toContain('日本01 · 香港中转');

    $v2 = base64_decode(app(SubscriptionService::class)->generateV2rayN($u->fresh()));
    expect($v2)->toContain('@1.1.1.1:30001')->not->toContain('9.9.9.9');
});
