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

// ── 写入路径 ────────────────────────────────────────────────────────────
//
// `[!!]` 上面那组只把页面打开，于是【整整一轮】没人发现：控制器 use 的
// App\Services\Audit 根本没跟着搬过来，规则的增/改/删/换凭据/生成密钥对
// 全是 Class not found 500，而 481 个用例照样全绿。
// 页面能打开 ≠ 能用 —— 写入路径必须自己有用例。

function ruleFormPayload(Node $n, array $over = []): array
{
    return array_merge([
        'name' => '新规则', 'enabled' => 1, 'speed_limit' => 0,
        'inbound_type' => 'direct', 'inbound_node_set' => [$n->id],
        'listen_port' => '30011', 'balance' => 'roundrobin',
        'backup_balance' => 'fallback', 'hc_enabled' => 0,
        'hc_interval_sec' => 30, 'hc_max_fail' => 3, 'hc_max_success' => 2,
        'out_type' => ['direct'], 'out_pool' => ['primary'], 'out_enabled' => [1],
        'out_target_addr' => ['9.9.9.9'], 'out_target_port' => ['443'],
    ], $over);
}

it('能新建规则,并且记进审计', function () {
    $n = pageRelayNode();
    $this->actingAs(relayAdminUser())->post('/admin/rules', ruleFormPayload($n))
        ->assertRedirect();

    expect(ForwardRule::where('name', '新规则')->exists())->toBeTrue();
    expect(\App\Models\AuditLog::where('action', 'rule.create')->exists())->toBeTrue();
});

it('能改规则,审计里写的是改了什么', function () {
    $n = pageRelayNode();
    $r = pageRule($n);
    $this->actingAs(relayAdminUser())
        ->put("/admin/rules/{$r->id}", ruleFormPayload($n, ['name' => '改名了', 'listen_port' => '30099']))
        ->assertRedirect();

    expect($r->fresh()->name)->toBe('改名了');
    $log = \App\Models\AuditLog::where('action', 'rule.update')->firstOrFail();
    expect($log->description)->toContain('listen_port');   // 记的是变更内容，不只是"被修改了"
    expect($log->target_type)->toBe('rule');               // 不是 @anonymous
});

it('能删规则', function () {
    $r = pageRule(pageRelayNode());
    $this->actingAs(relayAdminUser())->delete("/admin/rules/{$r->id}")->assertRedirect();
    expect(ForwardRule::find($r->id))->toBeNull();
    expect(\App\Models\AuditLog::where('action', 'rule.delete')->exists())->toBeTrue();
});

it('能重生成凭据与 REALITY 密钥对', function () {
    $r = pageRule(pageRelayNode());
    $admin = relayAdminUser();

    $this->actingAs($admin)->post("/admin/rules/{$r->id}/regenerate-cred")->assertRedirect();
    $this->actingAs($admin)->post("/admin/rules/{$r->id}/reality-keypair")->assertRedirect();

    expect($r->fresh()->inbound_opts['reality']['private_key'] ?? null)->not->toBeNull();
    expect(\App\Models\AuditLog::whereIn('action', ['rule.cred', 'rule.keypair'])->count())->toBe(2);
});

// `[!!]` 审计会被整表导出、贴进工单，读者比配置页广。
// 私钥/凭据落进 description 就等于泄露 —— 这条守的是 Audit::redact()。
it('审计不写私钥与凭据', function () {
    $n = pageRelayNode();
    $r = pageRule($n);
    $this->actingAs(relayAdminUser())->put("/admin/rules/{$r->id}", ruleFormPayload($n, [
        'name' => $r->name,
        'inbound_opts_reality_private_key' => 'PRIVATE-KEY-SHOULD-NOT-APPEAR',
    ]));

    foreach (\App\Models\AuditLog::all() as $log) {
        expect($log->description)->not->toContain('PRIVATE-KEY-SHOULD-NOT-APPEAR');
    }
});

// 后台建中转节点：此前表单没有 role 字段,新建的一律是 DB 默认 landing ——
// 也就是【后台根本建不出中转节点】,只能去数据库里改。
it('后台能建出中转节点并设额度', function () {
    $this->actingAs(relayAdminUser())->post('/admin/nodes', [
        'name' => '香港中转', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'sort' => 0,
        'role' => 'relay', 'quota_gb' => 500, 'quota_reset_day' => 5,
    ])->assertRedirect();

    $n = Node::where('name', '香港中转')->firstOrFail();
    expect($n->role)->toBe('relay');
    expect($n->quota_gb)->toBe(500);
    expect($n->quota_reset_day)->toBe(5);
});

it('role 只收白名单里的值', function () {
    $this->actingAs(relayAdminUser())->post('/admin/nodes', [
        'name' => 'X', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'sort' => 0,
        'role' => 'relayy',   // 打错一个字母 → 落到默认 landing → 中转拿到用户名单
    ])->assertSessionHasErrors('role');
});

// `[!]` port=0 只对中转成立。落地节点 port=0 会进订阅，客户端连不上
// 且没有任何报错 —— 用户只看到"连不上"。
it('落地节点不许 port=0', function () {
    $this->actingAs(relayAdminUser())->post('/admin/nodes', [
        'name' => 'X', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'sort' => 0,
        'role' => 'landing',
    ])->assertSessionHasErrors('port');
});

// ── D-1 的展示面 ────────────────────────────────────────────────────────
//
// `[!!]` 三处面向用户的查询(订阅 / 节点列表页 / 客户端 API)此前都只筛
// online + enabled,谁都没筛 role。中转 #93 因此【当时就摆在】节点列表和
// 客户端 API 里;没进订阅纯粹因为它 class=200 碰巧高于所有用户等级 ——
// 那是配置巧合,不是守卫。

function visibleRelayNode(): Node
{
    return Node::create([
        'name' => '中转别露出来', 'server' => '1.2.3.4', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'S',
        'role' => 'relay', 'online' => true, 'enabled' => true,
    ]);
}

function visibleLandingNode(): Node
{
    return Node::create([
        'name' => '落地看得见', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'L',
        'role' => 'landing', 'online' => true, 'enabled' => true,
    ]);
}

it('用户节点列表页不显示中转', function () {
    visibleRelayNode();
    visibleLandingNode();

    $this->actingAs(User::factory()->create())->get('/user/servers')
        ->assertOk()->assertSee('落地看得见')->assertDontSee('中转别露出来');
});

it('客户端 API 不返回中转', function () {
    visibleRelayNode();
    visibleLandingNode();

    apiUser();   // 客户端 API 走 Bearer api_token，不是会话
    $res = $this->getJson('/api/servers', ['Authorization' => 'Bearer TESTTOKEN123'])->assertOk();
    $names = collect($res->json('data'))->pluck('name')->all();
    expect($names)->toContain('落地看得见')->not->toContain('中转别露出来');
});

it('订阅里没有中转', function () {
    visibleRelayNode();
    visibleLandingNode();
    $u = apiUser();   // 订阅要求账号有效且有流量,用现成的 helper

    $body = app(\App\Services\SubscriptionService::class)->generateClash($u->fresh());
    expect($body)->toContain('落地看得见')->not->toContain('中转别露出来');
});

// ── 删节点 ──────────────────────────────────────────────────────────────
//
// `[!!]` 原来只挡 node_daily_traffic ——那是代理流量,中转根本不产生。
// 也就是说中转可以被直接删掉,而 rule_traffic / node_net_traffic 都是
// cascadeOnDelete,它的流量账会跟着消失。

it('有中转流量的节点删不掉', function () {
    $n = visibleRelayNode();
    \App\Models\NodeNetTraffic::create([
        'node_id' => $n->id, 'date' => now()->toDateString(), 'up' => 1, 'down' => 1,
    ]);

    $this->actingAs(relayAdminUser())->delete("/admin/nodes/{$n->id}")->assertRedirect();
    expect(Node::find($n->id))->not->toBeNull();
});

// `[!!]` inbound_node_set 是 JSON 列,没有外键 —— 删掉节点不会有任何报错,
// 规则里留下一个指向不存在节点的 id,那条规则悄悄少在一台机器上生效。
it('被规则引用的节点删不掉,并说出是哪条规则', function () {
    $n = pageRelayNode();
    $r = pageRule($n);

    $this->actingAs(relayAdminUser())->delete("/admin/nodes/{$n->id}")
        ->assertRedirect();
    expect(Node::find($n->id))->not->toBeNull();
    expect(session('status'))->toContain($r->name);
});

it('没有引用也没有流量的节点可以删', function () {
    $n = visibleRelayNode();
    $this->actingAs(relayAdminUser())->delete("/admin/nodes/{$n->id}")->assertRedirect();
    expect(Node::find($n->id))->toBeNull();
});

// `[!!]` 判据 29：搬了检查就渲染一次。这条提示在列表页有它自己的一档 ——
// 它的文案里也含"PROXY 头"，会被原来那个 $unsure 过滤器顺手抓走、
// 渲染成带问号的"未知"样式，而它不是未知，是确定会发生的事。
it('列表页把"本规则不承载 UDP"单独标出来', function () {
    $relay = pageRelayNode();
    $r = pageRule($relay);        // 出站 send_proxy_protocol = 2、裸端口转发

    $this->actingAs(relayAdminUser())->get('/admin/rules')
        ->assertOk()
        ->assertSee('本规则不承载 UDP');
});

it('不发 PROXY 头的规则不显示那条', function () {
    $relay = pageRelayNode();
    $r = pageRule($relay);
    $r->outbounds()->update(['send_proxy_protocol' => 0]);

    $this->actingAs(relayAdminUser())->get('/admin/rules')
        ->assertOk()
        ->assertDontSee('本规则不承载 UDP');
});

// ── 带数据渲染 ──────────────────────────────────────────────────────────
//
// `[!!]` 上面那组用的是空数据，于是"有数据才走到"的分支从没被执行过 ——
// App\Support\Fmt 整个类没跟着搬过来，两个页面在真实环境里一有流量就
// Class not found 500，而这组用例一直全绿。
// 判据 34 的具体形态：**渲染一个空页面，不算渲染过这个页面。**

function relayTrafficRow(ForwardRule $rule, Node $node): void
{
    \App\Models\RuleTraffic::create([
        'rule_id' => $rule->id, 'node_id' => $node->id, 'date' => today()->toDateString(),
        'up' => 1536, 'down' => 5 * 1024 * 1024,
    ]);
}

it('规则列表页在有流量数据时能渲染', function () {
    $n = pageRelayNode();
    $r = pageRule($n);
    relayTrafficRow($r, $n);

    $this->actingAs(relayAdminUser())->get('/admin/rules')
        ->assertOk()
        ->assertSee('5 MB');          // Fmt::bytes 真的被调用了
});

it('中转监控页在有整机流量时能渲染', function () {
    $n = pageRelayNode();
    \App\Models\NodeNetTraffic::create([
        'node_id' => $n->id, 'date' => today()->toDateString(),
        'up' => 2 * 1024 * 1024 * 1024, 'down' => 3 * 1024 * 1024 * 1024,
    ]);

    $this->actingAs(relayAdminUser())->get('/admin/relay/monitor')
        ->assertOk()
        ->assertSee('GB');
});

it('中转在线 IP 页在有记录时能渲染', function () {
    $n = pageRelayNode();
    $r = pageRule($n);
    \App\Models\RuleAliveIp::create([
        'rule_id' => $r->id, 'node_id' => $n->id, 'ip' => '203.0.113.7', 'last_seen' => now(),
    ]);

    $admin = relayAdminUser();

    // 汇总页：只出"哪条规则有多少来源"，不列 IP（agent 侧每条规则上限 4096，
    // 几条规则就是上万行，一次全加载没有意义）。
    $this->actingAs($admin)->get('/admin/relay/online-ip')
        ->assertOk()
        ->assertSee($r->name)
        ->assertDontSee('203.0.113.7');

    // 点进某一组才拉明细 —— IP 在这里。
    $this->actingAs($admin)->get("/admin/relay/online-ip?rule={$r->id}&node={$n->id}")
        ->assertOk()
        ->assertSee('203.0.113.7');
});

// Fmt 本身：进位与小数位的约定（1024 进制，字节数不带小数）
it('Fmt::bytes 的格式约定', function () {
    expect(\App\Support\Fmt::bytes(512))->toBe('512 B');        // 字节不带小数
    expect(\App\Support\Fmt::bytes(1536, 1))->toBe('1.5 KB');
    expect(\App\Support\Fmt::bytes(5 * 1024 * 1024))->toBe('5 MB');  // 尾随零去掉
});

// ── 节点列表：角色与额度 ────────────────────────────────────────────────
//
// `[!!]` 合并之后中转与落地在同一张表里(ADR-008)，不标角色就只能靠名字猜，
// 而这两类节点的运维含义完全不同：中转拿不到用户名单(D-1)、端口来自转发规则、
// 不进任何人的订阅。
it('节点列表标出角色,中转不显示 :0 端口', function () {
    pageRelayNode();                       // 中转，port=0
    visibleLandingNode();

    $html = $this->actingAs(relayAdminUser())->get('/admin/nodes')->assertOk()->getContent();

    expect($html)->toContain('中转')->toContain('落地')
        ->toContain('端口见转发规则')       // port=0 有解释，不是看着像配错了
        ->not->toContain('1.2.3.4:0');      // 也不写成 ":0"
});

// `[!!]` 额度此前只能在表单里【设】，没有任何页面显示【用了多少】——
// 而额度的用途正是防机房超量账单，看不见等于没设。
it('节点列表显示整机额度用量', function () {
    $n = pageRelayNode();
    $n->update(['quota_gb' => 100, 'quota_reset_day' => 1]);
    \App\Models\NodeNetTraffic::create([
        'node_id' => $n->id, 'date' => today()->toDateString(),
        'up' => 30 * 1024 ** 3, 'down' => 20 * 1024 ** 3,   // 50 GB / 100 GB = 50%
    ]);

    $this->actingAs(relayAdminUser())->get('/admin/nodes')
        ->assertOk()->assertSee('50%')->assertSee('/ 100 GB');
});

it('没设额度的节点显示"不限"', function () {
    visibleLandingNode();

    $this->actingAs(relayAdminUser())->get('/admin/nodes')->assertOk()->assertSee('不限');
});

// `[!]` 额度用量必须在控制器里一次算完:视图里逐行调 quotaPercent() 会让
// 每个设了额度的节点多两次查询(实测 12 个节点 5 → 29 次)。
it('额度用量不随节点数增加查询次数', function () {
    for ($i = 0; $i < 8; $i++) {
        Node::create([
            'name' => "n{$i}", 'server' => "10.1.1.{$i}", 'port' => 443, 'type' => 'vmess',
            'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => "S{$i}",
            'role' => 'landing', 'quota_gb' => 200, 'quota_reset_day' => 1,
        ]);
    }
    $admin = relayAdminUser();

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->actingAs($admin)->get('/admin/nodes')->assertOk();
    $n = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect($n)->toBeLessThan(15);   // 常数级；逐行查的话 8 个节点就 ~20+
});
