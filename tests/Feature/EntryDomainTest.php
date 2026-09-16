<?php

use App\Models\EntryDomain;
use App\Models\Node;
use App\Models\User;

/**
 * 入口域名池(v1:纯登记+提醒)。守四件事:
 *   · 只管理员能进、能改;
 *   · 入口域名只能前置【会转发的中转】(挂到落地没意义);
 *   · 一台中转至多一个「在用」—— 激活一个,其余降备用(订阅只发在用那个);
 *   · dnsStale 只在"指向 IP ≠ 中转真 IP"时告警,别误报;
 *   · CNAME 两层:改 A 记录要指到【标签】、标签不得自指、同标签的兄弟一起同步（见文件末尾）。
 */
function edAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function edRelay(string $ip = '1.1.1.1'): Node
{
    return Node::create([
        'name' => '香港中转', 'server' => $ip, 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'R'.$ip, 'role' => 'relay',
    ]);
}

it('列表页要管理员', function () {
    $this->get('/admin/entry-domains')->assertRedirect('/login');
});

it('普通用户打不动', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin/entry-domains')->assertForbidden();
});

it('管理员能看列表', function () {
    edRelay();
    $this->actingAs(edAdmin())->get('/admin/entry-domains')->assertOk()->assertSee('入口域名');
});

it('新增入口域名,默认备用', function () {
    $relay = edRelay();
    $this->actingAs(edAdmin())->post('/admin/entry-domains', [
        'domain' => 'cp.example.com', 'node_id' => $relay->id, 'pointed_ip' => '1.1.1.1',
    ])->assertRedirect('/admin/entry-domains');

    $ed = EntryDomain::first();
    expect($ed->domain)->toBe('cp.example.com');
    expect($ed->status)->toBe('standby');   // 新增默认备用,设为在用后订阅才发
});

// `[!!]` 入口域名只能前置会转发的中转;挂到落地节点上没有意义,必须挡。
it('不能把入口域名挂到落地节点', function () {
    $landing = Node::create([
        'name' => '落地', 'server' => '9.9.9.9', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'L', 'role' => 'landing',
    ]);
    $this->actingAs(edAdmin())->post('/admin/entry-domains', [
        'domain' => 'cp.example.com', 'node_id' => $landing->id,
    ])->assertStatus(422);
    expect(EntryDomain::count())->toBe(0);
});

it('重复域名被拒', function () {
    $relay = edRelay();
    EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id, 'status' => 'standby']);
    $this->actingAs(edAdmin())->from('/admin/entry-domains')->post('/admin/entry-domains', [
        'domain' => 'cp.example.com', 'node_id' => $relay->id,
    ])->assertRedirect('/admin/entry-domains')->assertSessionHasErrors('domain');
});

// `[!!]` 一台中转至多一个在用:激活一个,同中转其余在用的必须降为备用。
it('设为在用会把同中转其它在用降为备用', function () {
    $relay = edRelay();
    $a = EntryDomain::create(['domain' => 'a.example.com', 'node_id' => $relay->id, 'status' => 'active']);
    $b = EntryDomain::create(['domain' => 'b.example.com', 'node_id' => $relay->id, 'status' => 'standby']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$b->id}/activate")->assertRedirect();

    expect($a->fresh()->status)->toBe('standby');
    expect($b->fresh()->status)->toBe('active');
});

it('标记被墙改状态', function () {
    $relay = edRelay();
    $ed = EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id, 'status' => 'active']);
    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$ed->id}/block")->assertRedirect();
    expect($ed->fresh()->status)->toBe('blocked');
});

// 轮换 IP:登记新指向 + 回到在用 + 记轮换时间(提醒你去改 DNS,面板不代改)。
it('轮换IP登记新指向并回到在用', function () {
    $relay = edRelay();
    $ed = EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id, 'status' => 'blocked']);
    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$ed->id}/rotate", ['pointed_ip' => '5.6.7.8'])->assertRedirect();

    $ed->refresh();
    expect($ed->pointed_ip)->toBe('5.6.7.8');
    expect($ed->status)->toBe('active');
    expect($ed->last_rotated_at)->not->toBeNull();
});

it('轮换要合法 IP', function () {
    $relay = edRelay();
    $ed = EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id, 'status' => 'active']);
    $this->actingAs(edAdmin())->from('/admin/entry-domains')
        ->post("/admin/entry-domains/{$ed->id}/rotate", ['pointed_ip' => '不是IP'])
        ->assertSessionHasErrors('pointed_ip');
});

// dnsStale:只有"指向 IP ≠ 中转真 IP(且真 IP 是个 IP)"才告警。
it('dnsStale 只在指向与中转真IP不一致时为真', function () {
    $relay = edRelay('1.1.1.1');
    $same = EntryDomain::create(['domain' => 'a.example.com', 'node_id' => $relay->id, 'pointed_ip' => '1.1.1.1']);
    $diff = EntryDomain::create(['domain' => 'b.example.com', 'node_id' => $relay->id, 'pointed_ip' => '9.9.9.9']);
    $none = EntryDomain::create(['domain' => 'c.example.com', 'node_id' => $relay->id, 'pointed_ip' => null]);

    expect($same->fresh()->dnsStale())->toBeFalse();
    expect($diff->fresh()->dnsStale())->toBeTrue();
    expect($none->fresh()->dnsStale())->toBeFalse();   // 没登记指向,不误报
});

it('删除入口域名', function () {
    $relay = edRelay();
    $ed = EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id, 'status' => 'standby']);
    $this->actingAs(edAdmin())->delete("/admin/entry-domains/{$ed->id}")->assertRedirect();
    expect(EntryDomain::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────
// CNAME 两层结构（D-5 · docs/decisions/entry-dispatch.md）
//
//   门牌 entry.example.com  CNAME →  标签 hk1.example.net  A →  中转 IP
//
// 守三件事：
//   · 「要去改哪条 A 记录」必须指到【标签】，指错了管理员会去动门牌，把整层拆掉；
//   · 标签不得等于门牌（CNAME 环）；
//   · 轮换时共用同一标签的其它门牌【指向要一起同步】—— DNS 上它们本来就一起变了，
//     不同步就会亮出一批假的「该改 DNS」告警。
// ─────────────────────────────────────────────────────────────────────────

it('没配标签时，要改的就是门牌自己的 A 记录', function () {
    $ed = EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => edRelay()->id]);

    expect($ed->dnsRecordHost())->toBe('cp.example.com');
    expect($ed->isLayered())->toBeFalse();
});

it('配了标签时，要改的是标签的 A 记录', function () {
    $ed = EntryDomain::create([
        'domain' => 'cp.example.com', 'node_id' => edRelay()->id, 'cname_target' => 'hk1.example.net',
    ]);

    expect($ed->dnsRecordHost())->toBe('hk1.example.net');
    expect($ed->isLayered())->toBeTrue();
});

it('标签不能等于门牌（CNAME 环）', function () {
    $relay = edRelay();
    $this->actingAs(edAdmin())->from('/admin/entry-domains')->post('/admin/entry-domains', [
        'domain' => 'cp.example.com', 'node_id' => $relay->id, 'cname_target' => 'cp.example.com',
    ])->assertSessionHasErrors('cname_target');

    expect(EntryDomain::count())->toBe(0);
});

it('标签必须是合法主机名', function () {
    $relay = edRelay();
    $this->actingAs(edAdmin())->from('/admin/entry-domains')->post('/admin/entry-domains', [
        'domain' => 'cp.example.com', 'node_id' => $relay->id, 'cname_target' => '不是域名',
    ])->assertSessionHasErrors('cname_target');
});

it('能带标签新增', function () {
    $relay = edRelay();
    $this->actingAs(edAdmin())->post('/admin/entry-domains', [
        'domain' => 'cp.example.com', 'node_id' => $relay->id, 'cname_target' => 'hk1.example.net',
    ])->assertRedirect();

    expect(EntryDomain::first()->cname_target)->toBe('hk1.example.net');
});

// 轮换：同标签的兄弟一起同步指向（DNS 上它们本来就一起变了）
it('轮换把同标签的其它门牌一起同步指向', function () {
    $relay = edRelay('1.1.1.1');
    $a = EntryDomain::create(['domain' => 'a.example.com', 'node_id' => $relay->id,
        'cname_target' => 'hk1.example.net', 'pointed_ip' => '1.1.1.1', 'status' => 'active']);
    $b = EntryDomain::create(['domain' => 'b.example.com', 'node_id' => $relay->id,
        'cname_target' => 'hk1.example.net', 'pointed_ip' => '1.1.1.1', 'status' => 'standby']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$a->id}/rotate", ['pointed_ip' => '5.6.7.8']);

    expect($a->fresh()->pointed_ip)->toBe('5.6.7.8');
    expect($b->fresh()->pointed_ip)->toBe('5.6.7.8');   // 兄弟跟着走
});

it('轮换不碰不同标签、也不碰没标签的', function () {
    $relay = edRelay('1.1.1.1');
    $a = EntryDomain::create(['domain' => 'a.example.com', 'node_id' => $relay->id,
        'cname_target' => 'hk1.example.net', 'pointed_ip' => '1.1.1.1']);
    $other = EntryDomain::create(['domain' => 'c.example.com', 'node_id' => $relay->id,
        'cname_target' => 'hk2.example.net', 'pointed_ip' => '1.1.1.1']);
    $bare = EntryDomain::create(['domain' => 'd.example.com', 'node_id' => $relay->id,
        'pointed_ip' => '1.1.1.1']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$a->id}/rotate", ['pointed_ip' => '5.6.7.8']);

    expect($other->fresh()->pointed_ip)->toBe('1.1.1.1');
    expect($bare->fresh()->pointed_ip)->toBe('1.1.1.1');
});

// `[!]` 只同步指向，不动状态：被墙的门牌不该因为别人轮换就自动复活。
it('同步兄弟时不改它们的状态', function () {
    $relay = edRelay('1.1.1.1');
    $a = EntryDomain::create(['domain' => 'a.example.com', 'node_id' => $relay->id,
        'cname_target' => 'hk1.example.net', 'status' => 'standby']);
    $blocked = EntryDomain::create(['domain' => 'b.example.com', 'node_id' => $relay->id,
        'cname_target' => 'hk1.example.net', 'status' => 'blocked']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$a->id}/rotate", ['pointed_ip' => '5.6.7.8']);

    expect($blocked->fresh()->status)->toBe('blocked');
    expect($blocked->fresh()->pointed_ip)->toBe('5.6.7.8');
});

// 提示文案：指错了管理员就会去动门牌的 A 记录，而门牌是 CNAME，动它整层就没了。
it('轮换提示让你去改标签，并说明门牌不用动', function () {
    $relay = edRelay();
    $ed = EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id,
        'cname_target' => 'hk1.example.net']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$ed->id}/rotate", ['pointed_ip' => '5.6.7.8'])
        ->assertSessionHas('status', fn ($m) => str_contains($m, 'hk1.example.net')
            && str_contains($m, 'cp.example.com 是 CNAME'));
});

it('没标签时提示仍然指门牌', function () {
    $relay = edRelay();
    $ed = EntryDomain::create(['domain' => 'cp.example.com', 'node_id' => $relay->id]);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$ed->id}/rotate", ['pointed_ip' => '5.6.7.8'])
        ->assertSessionHas('status', fn ($m) => str_contains($m, 'cp.example.com 的 A 记录')
            && ! str_contains($m, 'CNAME'));
});
