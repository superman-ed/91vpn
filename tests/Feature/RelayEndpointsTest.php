<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ADR-008 P3：中转节点对接的五个端点（规则下发 + 四类上报）。
 *
 * `[!]` 这些端点服务的是【中转】角色。落地请求 /routes 会得到 404 ——
 * agent 把 404 解读为"本部署没有中转功能"并停止轮询，那是正确行为不是错误。
 */
function epRelayNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'relay', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'RELAYSECRET', 'role' => 'relay',
    ], $attr));
}

function epRuleOn(Node $n): ForwardRule
{
    $r = ForwardRule::create([
        'name' => 'r1', 'enabled' => true, 'listen_port' => '30001',
        'inbound_node_set' => [$n->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'target_addr' => '9.9.9.9', 'target_port' => '443',
        'trusted_transit' => true,
    ]);

    return $r->fresh('outbounds');
}

it('中转能拉到自己的规则，且下发带指纹', function () {
    $n = epRelayNode();
    epRuleOn($n);

    $res = $this->getJson("/mod_mu/nodes/{$n->id}/routes?node_id={$n->id}&key=RELAYSECRET")->assertOk();
    expect($res->json('data.config_hash'))->toMatch('/^[a-f0-9]{16}$/');
    expect($res->json('data.rules'))->toHaveCount(1);
});

// `[!]` 落地拿 404 是【正确行为】：agent 据此停止轮询转发规则。
it('落地请求规则端点得到 404', function () {
    $n = epRelayNode(['role' => 'landing', 'secret' => 'LANDSECRET']);
    $this->getJson("/mod_mu/nodes/{$n->id}/routes?node_id={$n->id}&key=LANDSECRET")
        ->assertStatus(404);
});

it('规则端点要认证', function () {
    $n = epRelayNode();
    $this->getJson("/mod_mu/nodes/{$n->id}/routes?node_id={$n->id}&key=WRONG")->assertStatus(401);
});

it('按规则的流量上报入库', function () {
    $n = epRelayNode();
    $r = epRuleOn($n);

    $this->postJson("/mod_mu/nodes/{$n->id}/rules/traffic?key=RELAYSECRET", [
        'data' => [['rule_id' => $r->id, 'u' => 1000, 'd' => 2000]],
    ])->assertOk();

    $row = DB::table('rule_traffic')->where('rule_id', $r->id)->first();
    expect((int) $row->up)->toBe(1000);
    expect((int) $row->down)->toBe(2000);
});

// `[decided]` D-1：中转不认证用户，所以这里【只有 IP，没有 user_id】。
it('按规则的来源 IP 上报入库，且不带用户身份', function () {
    $n = epRelayNode();
    $r = epRuleOn($n);

    $this->postJson("/mod_mu/nodes/{$n->id}/rules/aliveip?key=RELAYSECRET", [
        'data' => [['rule_id' => $r->id, 'ips' => ['203.0.113.5', '203.0.113.6']]],
    ])->assertOk();

    expect(DB::table('rule_alive_ip')->where('rule_id', $r->id)->count())->toBe(2);
    expect(DB::getSchemaBuilder()->hasColumn('rule_alive_ip', 'user_id'))
        ->toBeFalse('中转的在线 IP 表不该有用户身份（D-1）');
});

it('上游状态上报是快照，不是追加', function () {
    $n = epRelayNode();
    $r = epRuleOn($n);
    $post = fn (int $alive) => $this->postJson("/mod_mu/nodes/{$n->id}/rules/status?key=RELAYSECRET", [
        // [!] 键名以实现为准：outbounds / live（第一版写成 outs / conns，
        //     结果一行都没写进去而端点照样回 ok —— 是向量错了，不是实现错了）
        'data' => [['rule_id' => $r->id, 'outbounds' => [
            ['tag' => 'o0', 'dial' => '9.9.9.9:443', 'alive' => $alive, 'live' => 3]]]],
    ])->assertOk();

    $post(1);
    $post(0);
    expect(DB::table('rule_outbound_status')->where('rule_id', $r->id)->count())
        ->toBe(1, '同一个 (规则,节点,tag) 应当只有一行 —— 这是当前状态不是历史');
});

// `[!!]` 节点报一个面板没见过的哈希是**合法**的（它可能跑着上一版下发的规则），
// 那该显示成"落后"，而不是被丢弃 —— 丢弃会让它看起来"从未上报"，
// 与真的失联无法区分。
it('陌生哈希照常入库，畸形哈希不落库', function () {
    $n = epRelayNode();

    $this->postJson("/mod_mu/nodes/{$n->id}/rules/sync?key=RELAYSECRET", [
        'applied_hash' => 'deadbeefdeadbeef', 'fetched_hash' => 'deadbeefdeadbeef',
        'degraded' => false, 'rules' => 2,
    ])->assertOk();
    expect($n->fresh()->applied_hash)->toBe('deadbeefdeadbeef');

    $this->postJson("/mod_mu/nodes/{$n->id}/rules/sync?key=RELAYSECRET", [
        'applied_hash' => '不是十六进制', 'fetched_hash' => str_repeat('z', 40),
    ])->assertOk();
    expect($n->fresh()->applied_hash)->toBeNull('畸形哈希不该落库');
});

// 与 P0 的守卫合看：中转的规则面开放，用户面照旧关闭。
it('中转在规则端点通、在用户端点仍拿不到名单', function () {
    $n = epRelayNode();
    epRuleOn($n);
    $u = User::factory()->create([
        'class' => 5, 'class_expire' => now()->addDay(), 'transfer_enable' => 1024 ** 3,
    ]);

    $this->getJson("/mod_mu/nodes/{$n->id}/routes?node_id={$n->id}&key=RELAYSECRET")->assertOk();
    $body = $this->getJson("/mod_mu/users?node_id={$n->id}&key=RELAYSECRET")->assertOk()->getContent();
    expect($body)->not->toContain($u->uuid);
});

// `[!!]` nodeInfo 必须带 role：agent 靠它判断"本节点有没有自己的入站"。
// `[D]` ADR-008 P5 切换时真机撞到：不发这个字段，agent 把中转当落地解析，
// 而中转的 port 是 0 —— 每个拉取周期报一次 "port is required (got 0)"，
// 规则明明下发正常，节点却看起来是坏的。
it('nodeInfo 带上 role，中转不会被当成落地解析', function () {
    $n = epRelayNode();
    $res = $this->getJson("/mod_mu/nodes/{$n->id}/info?key=RELAYSECRET")->assertOk();
    expect($res->json('data.role'))->toBe('relay');

    $land = epRelayNode(['role' => 'landing', 'secret' => 'L2', 'port' => 34567]);
    expect($this->getJson("/mod_mu/nodes/{$land->id}/info?key=L2")->json('data.role'))
        ->toBe('landing');
});
