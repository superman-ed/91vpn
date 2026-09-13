<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\ForwardRuleService;
use App\Services\RuleCheck;

/**
 * 出站目标该用【节点引用】而不是裸地址。
 *
 * `[!!]` 节点引用是【面板侧】解析成 host:port 的，agent 根本看不到节点 ID ——
 * 所以两种写法下发的内容一字不差。差别全在面板这边能不能把这条出站
 * 认回到一个节点上：分层健康态、PROXY 头配对、换机器时跟着改，
 * 都要靠那个 ID。
 */
$GLOBALS['raSeq'] = 0;

function raNode(array $over = []): Node
{
    return Node::create(array_merge([
        'name' => '落地', 'server' => '203.0.113.'.(++$GLOBALS['raSeq']), 'port' => 39500,
        'type' => 'vless', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'RA'.$GLOBALS['raSeq'], 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function raRule(array $outbound): ForwardRule
{
    $relay = Node::create([
        'name' => '中转', 'server' => '198.51.100.'.(++$GLOBALS['raSeq']), 'port' => 0,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'RR'.$GLOBALS['raSeq'], 'role' => 'relay',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ]);
    $r = ForwardRule::create([
        'name' => 'r'.$GLOBALS['raSeq'], 'enabled' => true,
        'listen_port' => (string) (42000 + $GLOBALS['raSeq']),
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    ForwardOutbound::create(array_merge([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'trusted_transit' => true,
    ], $outbound));

    return $r->fresh('outbounds');
}

function raTexts(ForwardRule $r): string
{
    return collect(RuleCheck::check($r->fresh('outbounds')))->pluck('text')->implode("\n");
}

/**
 * `[!!]` 这一条先证实【缺口存在】。
 * 出站行在、enabled 也是真，但它指向一台【已停用】的落地 ——
 * 解析出来是空目标，compileRule 于是整条规则返回 null、不下发。
 * 规则连同它的【入站】一起消失，用户那条入口静默失效。
 * 而现有检查只数行数，不看解析结果，所以一句话都不会说。
 */
it('出站指向已停用的落地时，规则会被整条丢掉', function () {
    $landing = raNode();
    $rule = raRule(['target_node_set' => [$landing->id], 'target_port' => '39500']);
    $relay = Node::find($rule->inbound_node_set[0]);

    // 落地还在时：规则能编译出来
    expect(app(ForwardRuleService::class)->compileForNode($relay)['rules'])
        ->toHaveCount(1);

    $landing->update(['enabled' => false]);

    // 落地停用后：整条规则消失（连入站一起）—— 用户那条入口静默失效。
    expect(app(ForwardRuleService::class)->compileForNode($relay->fresh())['rules'])
        ->toHaveCount(0);
});

it('规则被整条丢掉之前，面板要先说出来', function () {
    $landing = raNode();
    $rule = raRule(['target_node_set' => [$landing->id], 'target_port' => '39500']);
    expect(raTexts($rule))->not->toContain('解析不出任何拨号目标');

    $landing->update(['enabled' => false]);

    $t = raTexts($rule);
    expect($t)->toContain('解析不出任何拨号目标')
        // 后果要说全：不只是这条出站，是【整条规则连同入站】。
        ->toContain('连它的入站一起')
        // 原因要具体到是哪一台
        ->toContain("#{$landing->id}")
        ->toContain('已停用');
});

it('还有别的出站能拨时不报 —— 规则仍会下发', function () {
    $a = raNode();
    $b = raNode();
    $rule = raRule(['target_node_set' => [$a->id], 'target_port' => '39500']);
    ForwardOutbound::create([
        'rule_id' => $rule->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'trusted_transit' => true,
        'target_node_set' => [$b->id], 'target_port' => '39500',
    ]);
    $a->update(['enabled' => false]);

    expect(raTexts($rule->fresh('outbounds')))->not->toContain('解析不出任何拨号目标');
});

it('落地节点没有端口而出站也没写时，要说清是这个原因', function () {
    // 中转节点的 port 是 0 —— 拿它当出站目标又不写端口，会拼出畸形拨号，
    // 所以面板会丢弃。此前这同样是静默的。
    $relayAsTarget = raNode(['role' => 'relay', 'port' => 0]);
    $rule = raRule(['target_node_set' => [$relayAsTarget->id]]);

    expect(raTexts($rule))->toContain('没有端口');
});

/**
 * 裸地址：两档分开说，因为处置不同。
 */
it('写死的地址能对上面板里的节点时，指名让他改成引用', function () {
    $landing = raNode();
    $rule = raRule(['target_addr' => $landing->server, 'target_port' => '39500']);

    $t = raTexts($rule);
    expect($t)->toContain("#{$landing->id}")
        ->toContain('改成【选落地节点】')
        // 关键是说清楚"改了不会有副作用" —— 否则没人敢动生产配置
        ->toContain('一字不差');
});

it('写死的地址对不上任何节点时，说清楚面板管不到它', function () {
    $rule = raRule(['target_addr' => '192.0.2.77', 'target_port' => '443']);

    $t = raTexts($rule);
    expect($t)->toContain('面板管不到的目标')
        ->toContain('没有健康态')
        // 不能一刀切说"禁止" —— 外部目标是合法用法
        ->toContain('确实是外部目标就保持现状');
});

it('已经用了节点引用的出站不再唠叨地址字段', function () {
    $landing = raNode();
    $rule = raRule([
        'target_node_set' => [$landing->id], 'target_port' => '39500',
        'target_addr' => $landing->server,   // 死字段:节点集优先
    ]);

    expect(raTexts($rule))->not->toContain('写死了地址');
});

/**
 * 一键收编。`[!!]` 关键性质：【下发给节点的内容一字不变】。
 * 这也是敢对生产配置按这个按钮的唯一理由 —— 所以用测试钉死它。
 */
it('转成节点引用之后，下发给节点的内容一字不变', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $landing = raNode();
    $rule = raRule(['target_addr' => $landing->server, 'target_port' => '39500']);
    $relay = Node::find($rule->inbound_node_set[0]);

    $before = app(ForwardRuleService::class)->compileForNode($relay);

    $this->actingAs($admin)->post("/admin/rules/{$rule->id}/adopt-targets")->assertRedirect();

    $after = app(ForwardRuleService::class)->compileForNode($relay->fresh());

    // `[!]` 比 config_hash，不做深比较。深比较会被 stdClass 的【对象身份】
    // 绊住（credential 是空 stdClass，每次编译都是新实例，内容完全一样）。
    // 而 config_hash 正是系统自己用来判断"变没变"的指纹 —— 语义也更贴。
    expect($after['config_hash'])->toBe($before['config_hash']);
    expect($after['rules'][0]['outbounds'][0]['dial'])
        ->toBe($before['rules'][0]['outbounds'][0]['dial']);

    expect($rule->fresh('outbounds')->outbounds->first()->target_node_set)->toBe([$landing->id]);
});

it('对不上唯一一台时不动它 —— 认错了人比认不出来更糟', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    // 同 IP 同端口的两台落地：分不清该引用哪一台。
    $a = raNode();
    $b = raNode(['server' => $a->server, 'port' => $a->port]);
    $rule = raRule(['target_addr' => $a->server, 'target_port' => (string) $a->port]);

    $this->actingAs($admin)->post("/admin/rules/{$rule->id}/adopt-targets")
        ->assertRedirect()
        ->assertSessionHas('status', fn ($m) => str_contains($m, '对上了 2 台'));

    expect($rule->fresh('outbounds')->outbounds->first()->target_node_set)->toBeIn([null, []]);
});

it('转换要留审计 —— 这是改了别人配置的动作', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $landing = raNode();
    $rule = raRule(['target_addr' => $landing->server, 'target_port' => '39500']);

    $this->actingAs($admin)->post("/admin/rules/{$rule->id}/adopt-targets");

    expect(\DB::table('audit_logs')->where('action', 'adopt_target')->count())->toBe(1);
});
