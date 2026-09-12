<?php

use App\Models\AuditLog;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\User;

/**
 * 中转链路向导。
 *
 * `[!!]` 它存在的理由是替人做掉【打开落地 accept_proxy】那一步：
 * 中转发 PROXY 头、落地就必须收头，两件事必须成对，而手工配时这一步在
 * 另一个页面。漏掉的表现是中转日志正常、落地一行都没有、只有客户端连不上
 * （sogacore compatibility/b2-reality-through-relay.md §2.5）。
 * 所以这组用例最核心的一条是"落地的开关真的被打开了"。
 */
function wizAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function wizRelay(string $ip = '1.1.1.1'): Node
{
    return Node::create([
        'name' => '香港中转', 'server' => $ip, 'port' => 0, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'R'.$ip, 'role' => 'relay',
        'online' => true, 'enabled' => true,
    ]);
}

function wizLanding(int $port = 443, bool $accept = false): Node
{
    return Node::create([
        'name' => '日本01', 'server' => '9.9.9.9', 'port' => $port, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'L'.$port,
        'role' => 'landing', 'online' => true, 'enabled' => true,
        'accept_proxy_protocol' => $accept,
    ]);
}

it('向导页能打开', function () {
    wizRelay();
    wizLanding();

    $this->actingAs(wizAdmin())->get('/admin/rules/wizard')
        ->assertOk()->assertSee('香港中转')->assertSee('日本01');
});

it('没有中转节点时给出去建节点的指引,而不是一个空下拉框', function () {
    wizLanding();

    $this->actingAs(wizAdmin())->get('/admin/rules/wizard')
        ->assertOk()->assertSee('还没有')->assertSee('添加节点');
});

// `[!!]` 这条是向导存在的全部理由。
it('创建链路时自动打开落地的 accept_proxy', function () {
    $relay = wizRelay();
    $landing = wizLanding(accept: false);   // 一开始【没】开

    $this->actingAs(wizAdmin())->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ])->assertRedirect('/admin/rules');

    $rule = ForwardRule::firstOrFail();
    expect($rule->inbound_node_set)->toBe([$relay->id]);
    expect($rule->listen_port)->toBe('30001');

    $ob = $rule->outbounds->first();
    expect($ob->target_addr)->toBe('9.9.9.9');
    expect((int) $ob->target_port)->toBe(443);
    expect((int) $ob->send_proxy_protocol)->toBe(2);

    // 核心断言:落地的开关被替他打开了
    expect($landing->fresh()->accept_proxy_protocol)->toBeTrue();
});

// `[!!]` 开了收头就必须锁端口 —— PROXY 头无认证。这句提醒必须出现,
// 否则向导等于替人开了个洞还不告诉他。
it('自动开了收头时,提醒去锁落地端口', function () {
    $relay = wizRelay();
    $landing = wizLanding();

    $this->actingAs(wizAdmin())->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ]);

    // 断言意图而非具体措辞:提醒锁端口只放行中转 + 讲清无认证会被伪造 +
    // 指向一键部署自动配防火墙(而不是叫人手敲 iptables)。
    expect(session('status'))
        ->toContain('只放行中转')
        ->toContain('伪造')
        ->toContain('部署');
});

// `[!!]` 给【已经开着收头】的落地再挂一个中转:accept_proxy 不用再翻,
// 但防火墙提醒【仍必须出现】,而且要把新中转的 IP 一并列进放行清单 ——
// 否则第二个中转的流量要么被挡、要么运维根本不知道要加它。
it('给已开收头的落地再加中转,仍提醒防火墙并列出全部中转 IP', function () {
    $landing = wizLanding();
    $relay1 = wizRelay('1.1.1.1');
    $relay2 = wizRelay('2.2.2.2');
    $admin = wizAdmin();

    $this->actingAs($admin)->post('/admin/rules/wizard', [
        'relay_id' => $relay1->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ]);
    expect($landing->fresh()->accept_proxy_protocol)->toBeTrue();

    // 第二条:同一落地、另一台中转。此时 accept_proxy 已开(不翻)。
    $this->actingAs($admin)->post('/admin/rules/wizard', [
        'relay_id' => $relay2->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ]);
    expect(session('status'))
        ->toContain('只放行中转')       // 提醒仍在
        ->toContain('1.1.1.1')          // 两个中转
        ->toContain('2.2.2.2');         // 都在放行清单里
});

it('不发 PROXY 头时不动落地的开关', function () {
    $relay = wizRelay();
    $landing = wizLanding(accept: false);

    $this->actingAs(wizAdmin())->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30001,   // send_proxy 不勾
    ])->assertRedirect();

    expect($landing->fresh()->accept_proxy_protocol)->toBeFalse();
    expect((int) ForwardRule::firstOrFail()->outbounds->first()->send_proxy_protocol)->toBe(0);
});

// `[!]` 同一台中转上两条规则抢一个端口:节点侧表现为后一条起不来,
// 而面板看着两条都"已启用"。
it('端口在这台中转上被占用时拒绝', function () {
    $relay = wizRelay();
    $landing = wizLanding();
    $admin = wizAdmin();

    $this->actingAs($admin)->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ]);

    $this->actingAs($admin)->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ])->assertSessionHasErrors('listen_port');

    expect(ForwardRule::count())->toBe(1);
});

it('端口落在已有规则的范围里也算占用', function () {
    $relay = wizRelay();
    $landing = wizLanding();
    ForwardRule::create([
        'name' => '已有', 'enabled' => true, 'inbound_type' => 'direct',
        'inbound_node_set' => [$relay->id], 'listen_port' => '30000-30010',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);

    $this->actingAs(wizAdmin())->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30005, 'send_proxy' => 1,
    ])->assertSessionHasErrors('listen_port');
});

it('落地没有端口时拒绝,并说清为什么', function () {
    $relay = wizRelay();
    $landing = wizLanding(port: 0);

    $this->actingAs(wizAdmin())->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ])->assertSessionHasErrors('landing_id');
});

it('选了中转当落地时拒绝', function () {
    $relay = wizRelay();
    $relay2 = wizRelay('2.2.2.2');

    $this->actingAs(wizAdmin())->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $relay2->id,
        'listen_port' => 30001,
    ])->assertSessionHasErrors('landing_id');
});

it('替落地改开关这件事要进审计', function () {
    $relay = wizRelay();
    $landing = wizLanding();

    $this->actingAs(wizAdmin())->post('/admin/rules/wizard', [
        'relay_id' => $relay->id, 'landing_id' => $landing->id,
        'listen_port' => 30001, 'send_proxy' => 1,
    ]);

    // 向导替人动了另一个对象的配置 —— 这种"顺手改了别的东西"尤其要留痕
    expect(AuditLog::where('description', 'like', '%accept_proxy%')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'rule.create')->exists())->toBeTrue();
});
