<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\User;
use App\Services\NodeDiagnosis;

/**
 * 节点一键诊断。
 *
 * `[!!]` 它存在的理由是抓【某一项绿着、另一项才是真因】的组合 ——
 * 这些线索本来都查得到，但分别在节点列表、规则列表、订阅和节点机的
 * journalctl 里，没人会同时打开四个地方。
 */
function dxAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

$GLOBALS['dxSeq'] = 0;

function dxNode(array $over = []): Node
{
    return Node::create(array_merge([
        'name' => '诊断用', 'server' => '203.0.113.199', 'port' => 39500,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        // [!] secret 有唯一约束:用递增计数而不是随机数 —— 随机会偶发撞车,
        // 而那种失败看着像被测代码的问题。
        'secret' => 'S'.(++$GLOBALS['dxSeq']), 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 10,
    ], $over));
}

/** @return array<string,array{level:string,detail:string}> 按 title 索引 */
function dxRun(Node $n): array
{
    $out = [];
    foreach (app(NodeDiagnosis::class)->run($n) as $i) {
        $out[$i['title']] = $i;
    }

    return $out;
}

it('心跳正常时报 ok,失联时报 bad 并指出去哪看', function () {
    expect(dxRun(dxNode())['心跳']['level'])->toBe('ok');

    $stale = dxRun(dxNode(['last_heartbeat' => time() - 600]))['心跳'];
    expect($stale['level'])->toBe('bad');
    expect($stale['detail'])->toContain('journalctl');
});

// [!] 列是 NOT NULL,"从没上报过"在库里是 0 而不是 null。
it('从未上报过心跳时,说清可能是哪几处配错', function () {
    $r = dxRun(dxNode(['last_heartbeat' => 0]))['心跳'];

    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('webapi_url');
});

// `[!!]` 这条是整个诊断最重要的一项:端口没放行时,心跳/在线状态【全是绿的】,
// 因为心跳是节点往外发的,不需要入站放行。
it('端口连不上时报 bad,并点明"这一项不通时心跳仍然正常"', function () {
    $r = dxRun(dxNode())['端口'];   // 203.0.113.x 是保留地址，必然连不上

    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('ufw');
    expect($r['detail'])->toContain('安全组');
    expect($r['detail'])->toContain('心跳仍然正常');
});

it('落地没有端口时报 warn', function () {
    expect(dxRun(dxNode(['port' => 0]))['端口']['level'])->toBe('warn');
});

it('REALITY 的 dest 挂了时,说明"端口在听但没人能握手"', function () {
    $n = dxNode([
        'type' => 'vless', 'flow' => 'xtls-rprx-vision',
        'reality_dest' => 'x.example:443', 'reality_private_key' => 'k',
        'reality_public_key' => 'p', 'reality_short_ids' => ['ab'],
        'reality_server_names' => ['x.example'],
        'reported_dest' => 'x.example:443', 'reported_dest_up' => false,
        'reported_dest_failures' => 5, 'dest_reported_at' => now(),
    ]);
    $r = dxRun($n)['REALITY dest'];

    expect($r['level'])->toBe('bad');
    // `[!]` 措辞要说清【包括密钥正确的老用户】—— 直觉模型("验不过才去连 dest")
    // 会让人以为"至少密钥对的人还能连",而实际是全量失败。
    expect($r['detail'])->toContain('包括密钥正确的老用户');
    expect($r['detail'])->toContain('握手根本没机会开始');
});

it('非 REALITY 节点不报 dest 那一项', function () {
    expect(dxRun(dxNode()))->not->toHaveKey('REALITY dest');
});

// `[!!]` 面板配了收头而节点实际没在收 —— 中转发来的连接会全断,且两侧都不报错。
it('PROXY 头配置与实际不一致时报 bad', function () {
    $n = dxNode([
        'accept_proxy_protocol' => true,
        'reported_accept_proxy' => false,
        'accept_proxy_reported_at' => now(),
    ]);
    $r = dxRun($n)['PROXY 头'];

    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('两侧都不报错');
});

it('PROXY 头两边一致时报 ok', function () {
    $n = dxNode([
        'accept_proxy_protocol' => true,
        'reported_accept_proxy' => true,
        'accept_proxy_reported_at' => now(),
    ]);

    expect(dxRun($n)['PROXY 头']['level'])->toBe('ok');
});

it('用户可见性:给出订阅里实际有几条入口', function () {
    $r = dxRun(dxNode())['用户可见性'];

    expect($r['level'])->toBe('ok');
    expect($r['detail'])->toContain('1 条入口');
    expect($r['detail'])->toContain('等级门槛');
});

// `[!!]` accept_proxy 的落地不发直连条目,而如果又没有中转指向它,
// 用户订阅里就【一条都没有】—— 节点跑得好好的,却没人能用。
it('开了收头又没有中转指向时,指出订阅里一条入口都没有', function () {
    $n = dxNode(['accept_proxy_protocol' => true]);
    $r = dxRun($n)['用户可见性'];

    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('一条入口都没有');
});

it('禁用的节点说明它不进订阅但照常运行', function () {
    $r = dxRun(dxNode(['enabled' => false]))['用户可见性'];

    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('节点本身照常运行');
});

it('中转:逐条规则探它的监听端口', function () {
    $relay = dxNode(['role' => 'relay', 'port' => 0, 'server' => '203.0.113.198']);
    $rule = ForwardRule::create([
        'name' => '某条规则', 'enabled' => true, 'listen_port' => '30001',
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    ForwardOutbound::create([
        'rule_id' => $rule->id, 'pool' => 'primary', 'enabled' => true, 'out_type' => 'direct',
        'target_addr' => '9.9.9.9', 'target_port' => '443', 'trusted_transit' => true,
    ]);

    $r = dxRun($relay);
    expect($r['端口']['detail'])->toContain('某条规则')->toContain('30001');
    expect($r['用户可见性']['detail'])->toContain('中转不进订阅');
});

it('中转上没有启用的规则时,说明它什么都不监听', function () {
    $relay = dxNode(['role' => 'relay', 'port' => 0]);
    $r = dxRun($relay)['端口'];

    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('什么都不监听');
});

it('诊断端点返回 JSON,普通用户进不去', function () {
    $n = dxNode();

    $this->actingAs(dxAdmin())->getJson("/admin/nodes/{$n->id}/diagnose")
        ->assertOk()->assertJsonStructure(['node', 'at', 'items' => [['level', 'title', 'detail']]]);

    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->getJson("/admin/nodes/{$n->id}/diagnose")->assertForbidden();
});

it('节点列表有诊断按钮', function () {
    dxNode();

    $this->actingAs(dxAdmin())->get('/admin/nodes')->assertOk()->assertSee('诊断');
});
