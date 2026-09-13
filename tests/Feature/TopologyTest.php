<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\RuleOutboundStatus;
use App\Models\User;
use App\Services\Topology;

/**
 * 拓扑视图。
 *
 * `[!!]` 这一页要回答「现在坏了的话，是哪一段」和「用户实际拿得到哪几条路」，
 * 不是「我们有几台机器」。所以用例守的是这两个问题的答案，
 * 而不是页面上有没有画出连线。
 */
$GLOBALS['tpSeq'] = 0;

function tpLanding(array $over = []): Node
{
    return Node::create(array_merge([
        'name' => 'L'.(++$GLOBALS['tpSeq']), 'server' => '203.0.113.'.$GLOBALS['tpSeq'],
        'port' => 39500, 'type' => 'vless', 'net' => 'tcp', 'traffic_rate' => 1,
        'node_class' => 0, 'secret' => 'TP'.$GLOBALS['tpSeq'], 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function tpRelay(array $over = []): Node
{
    return Node::create(array_merge([
        'name' => 'R'.(++$GLOBALS['tpSeq']), 'server' => '198.51.100.'.$GLOBALS['tpSeq'],
        'port' => 0, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1,
        'node_class' => 0, 'secret' => 'TP'.$GLOBALS['tpSeq'], 'role' => 'relay',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function tpRule(Node $relay, Node $landing, array $over = []): ForwardRule
{
    $r = ForwardRule::create(array_merge([
        'name' => 'rule'.(++$GLOBALS['tpSeq']), 'enabled' => true,
        'listen_port' => (string) (48000 + $GLOBALS['tpSeq']),
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ], $over));
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'target_node_set' => [$landing->id],
        'target_port' => (string) $landing->port, 'send_proxy_protocol' => 2,
        'trusted_transit' => true,
    ]);

    return $r->fresh('outbounds');
}

function tpHop(ForwardRule $rule, Node $relay, bool $alive, int $delay = 5, bool $slow = false): void
{
    static $t = 0;
    RuleOutboundStatus::create([
        'rule_id' => $rule->id, 'node_id' => $relay->id, 'tag' => 'o'.(++$t),
        'dial' => '203.0.113.1:39500', 'backup' => false, 'alive' => $alive,
        'live' => 0, 'delay_ms' => $delay, 'slow' => $slow, 'reported_at' => now(),
    ]);
}

function tpBuild(): array
{
    return app(Topology::class)->build();
}

it('一条经中转的路径被完整展开', function () {
    $L = tpLanding(['accept_proxy_protocol' => true]);
    $R = tpRelay();
    $rule = tpRule($R, $L);
    tpHop($rule, $R, true, 12);

    $t = tpBuild();
    expect($t['landings'])->toHaveCount(1);
    $paths = $t['landings'][0]['paths'];
    expect($paths)->toHaveCount(1)
        ->and($paths[0]['kind'])->toBe('relay')
        ->and($paths[0]['relay']->id)->toBe($R->id)
        ->and($paths[0]['hop']['state'])->toBe('ok')
        ->and($paths[0]['hop']['delay_ms'])->toBe(12)
        // 规则要能点进去 —— 看见问题之后下一步就是去改它
        ->and($paths[0]['hop']['rule']->id)->toBe($rule->id);
});

it('订阅里的每一条，拓扑里都有且标为「在订阅中」', function () {
    // `[!!]` 拓扑要是自己算一套可达性，它和用户实际拿到的就会漂。
    $L = tpLanding();           // 不收 PROXY 头 → 既有直连也有中转
    $R = tpRelay();
    tpHop(tpRule($R, $L), $R, true);

    $entries = app(\App\Services\SubscriptionService::class)->entrypoints($L->fresh());
    $inSub = collect(tpBuild()['landings'][0]['paths'])->where('in_sub', true);

    expect($inSub)->toHaveCount(count($entries));
    foreach ($entries as $e) {
        expect($inSub->contains(fn ($p) => $p['server'] === $e['server'] && $p['port'] === $e['port']))
            ->toBeTrue("订阅里的 {$e['server']}:{$e['port']} 在拓扑里找不到");
    }
});

it('没有任何路径的落地被单独标出来 —— 节点列表上它照样显示在线', function () {
    // `[!!]` 这是最值得一眼看到的状态。
    $L = tpLanding(['accept_proxy_protocol' => true]);   // 不发直连,又没有中转

    $t = tpBuild();
    expect($t['landings'][0]['unreachable'])->toBeTrue()
        ->and($t['stats']['unreachable'])->toBe(1);
    // 而它自己是"在线"的
    expect($L->fresh()->online)->toBeTrue();
});

it('到落地不通时，路径【仍然列出来】并标红，而不是消失', function () {
    // `[!!]` 订阅会把这条入口摘掉（对用户是对的），但对看拓扑的人是最坏的：
    // 他要看的正是"本该有、现在不通"的那一条。所以拓扑按配置枚举，不从订阅派生。
    $L = tpLanding(['accept_proxy_protocol' => true]);
    $R = tpRelay();
    tpHop(tpRule($R, $L), $R, false);

    $t = tpBuild();
    $p = $t['landings'][0]['paths'][0];
    expect($p['hop']['state'])->toBe('down')
        ->and($p['in_sub'])->toBeFalse()
        ->and($p['why_not'])->toContain('订阅已自动摘掉')
        // 中转自己还活着 —— 分层的意义就在这里
        ->and($t['landings'][0]['layers']['relay']['state'])->toBe('ok');
    // 配置里有路但全都不通 ⇒ 对用户等同于"拿不到"
    expect($t['landings'][0]['unreachable'])->toBeTrue();
});

it('闲置的中转要说清楚为什么闲置', function () {
    // `[!]` 只标一个"闲置"的话,人还得自己去四个地方翻。
    $unbound = tpRelay();
    $offline = tpRelay(['online' => false, 'last_heartbeat' => time() - 99999]);
    $disabled = tpRelay(['enabled' => false]);

    $why = collect(tpBuild()['orphans'])->keyBy(fn ($o) => $o['node']->id);
    expect($why[$unbound->id]['why'])->toContain('没有任何转发规则')
        ->and($why[$offline->id]['why'])->toContain('心跳失联')
        ->and($why[$disabled->id]['why'])->toContain('已停用');
});

it('承载了路径的中转不算闲置', function () {
    $L = tpLanding(['accept_proxy_protocol' => true]);
    $R = tpRelay();
    tpHop(tpRule($R, $L), $R, true);

    expect(tpBuild()['orphans'])->toBe([]);
});

it('落地停用时，路径仍然列出来并说明是落地的问题', function () {
    // `[!!]` 早先这里把中转算成"闲置"，那是【指错了地方】——
    // 中转好好的，问题在落地。现在路径照列，原因写在路径上。
    $L = tpLanding(['accept_proxy_protocol' => true]);
    $R = tpRelay();
    tpRule($R, $L);
    $L->update(['enabled' => false]);

    $t = tpBuild();
    $p = $t['landings'][0]['paths'][0];
    expect($p['in_sub'])->toBeFalse()
        ->and($p['why_not'])->toContain('落地已停用')
        // 中转不该被算成闲置 —— 它承载着一条配置好的路径
        ->and($t['orphans'])->toBe([]);
});

it('两个判定都要过才算用户拿得到 —— 有路径 ≠ 会下发', function () {
    // entrypoints() 只答"怎么到得了"；"会不会下发"在 accessibleNodes。
    // 只看前者会得出"停用的落地也在订阅里"。
    $L = tpLanding(['accept_proxy_protocol' => true]);
    $R = tpRelay();
    tpHop(tpRule($R, $L), $R, true);
    expect(tpBuild()['landings'][0]['unreachable'])->toBeFalse();

    $L->update(['online' => false]);
    expect(tpBuild()['landings'][0]['unreachable'])->toBeTrue();
});

it('页面打得开，且把两页的分工写清楚', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $L = tpLanding(['accept_proxy_protocol' => true]);
    $R = tpRelay();
    tpHop(tpRule($R, $L), $R, true, 8);

    $this->actingAs($admin)->get('/admin/topology')->assertOk()
        // `[!]` 不断言「按路径看」—— 视图里是 按<strong>路径</strong>看，
        // 标签把它拆开了，字面量查不到。查一句完整不被拆的。
        ->assertSee('现在坏了的话，是哪一段', false)
        ->assertSee($R->name)
        ->assertSee('到落地 8ms')
        // 这一跳面板测不了,必须写出来 —— 否则人会去 ping 落地再据此下结论
        ->assertSee('只有中转自己测得了', false);
});

it('运维进得去，运营也进得去（只读），财务进不去', function () {
    foreach (['infra' => 200, 'ops' => 200, 'finance' => 403] as $role => $code) {
        $u = User::factory()->create(['is_admin' => true, 'admin_role' => $role]);
        $this->actingAs($u)->get('/admin/topology')->assertStatus($code);
    }
});
