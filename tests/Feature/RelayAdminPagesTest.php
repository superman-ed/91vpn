<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\User;

/**
 * ADR-008 P4：中转的三个后台页搬进本面板后能真渲染。
 *
 * `[!]` 视图搬家最容易在"引用了原面板才有的东西"上露馅（布局名、路由名、
 * 只有那边有的模型或辅助函数）——而这类错误只有【真渲染一次】才看得见，
 * 语法检查是过的。所以这组用例只做一件事：把页面打开。
 */
function relayAdminUser(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function pageRelayNode(): Node
{
    return Node::create([
        'name' => '香港中转', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'S', 'role' => 'relay',
    ]);
}

function pageRule(Node $n): ForwardRule
{
    $r = ForwardRule::create([
        'name' => '跨境中转', 'enabled' => true, 'listen_port' => '30001',
        'inbound_node_set' => [$n->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'target_addr' => '9.9.9.9', 'target_port' => '443',
        'trusted_transit' => true, 'send_proxy_protocol' => 2,
    ]);

    return $r->fresh('outbounds');
}

it('规则列表页能打开', function () {
    $n = pageRelayNode();
    pageRule($n);
    $this->actingAs(relayAdminUser())->get('/admin/rules')->assertOk()->assertSee('跨境中转');
});

it('新建规则页能打开', function () {
    pageRelayNode();
    $this->actingAs(relayAdminUser())->get('/admin/rules/create')->assertOk();
});

it('编辑规则页能打开', function () {
    $r = pageRule(pageRelayNode());
    $this->actingAs(relayAdminUser())->get("/admin/rules/{$r->id}/edit")->assertOk()->assertSee('跨境中转');
});

it('中转监控页能打开', function () {
    pageRelayNode();
    $this->actingAs(relayAdminUser())->get('/admin/relay/monitor')->assertOk();
});

it('中转在线 IP 页能打开', function () {
    pageRelayNode();
    $this->actingAs(relayAdminUser())->get('/admin/relay/online-ip')->assertOk();
});

// 与其它后台页一致：非管理员进不去。
// `[!]` 拆成两条：actingAs 会在整个用例里一直生效，
// 同一条里再 get 一次仍然是那个已登录用户 —— 第一版因此把
// "游客应当跳转登录"测成了 403。
it('普通用户打不开中转页面', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin/rules')->assertForbidden();
});

it('游客打开中转页面会跳去登录', function () {
    $this->get('/admin/rules')->assertRedirect('/login');
});

// `[!!]` 配对校验在列表页要看得见 —— 它是这条链路上唯一能在出事【之前】
// 发现"发头/收头错开"的地方（sogacore b2-reality-through-relay.md §2.5）。
it('列表页把配对错配标出来', function () {
    $n = pageRelayNode();
    Node::create([   // 落地：报的是"没在收头"，而规则发 v2
        'name' => '落地', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'L',
        'role' => 'landing', 'accept_proxy_protocol' => false,
        'reported_accept_proxy' => false, 'accept_proxy_reported_at' => now(),
    ]);
    pageRule($n);

    $this->actingAs(relayAdminUser())->get('/admin/rules')->assertOk()->assertSee('会下发但用户连不上');
});
