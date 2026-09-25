<?php

use App\Models\EntryDomain;
use App\Models\Node;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────
// `[!!]` Node::activeEntryDomain() 取 where('status','active')->first(),
//   注释声称"一台中转至多一个 active"。那个不变式【只靠控制器维护】——
//   activate() 会把同节点其它 active 降为 standby,而 rotate() 曾经
//   直接置 active 而【不降其它】。
//   [D] 2026-09-24 实测:轮换备用域名后同节点出现两个 active,
//       而 first() 按插入顺序返回 → 订阅仍在发【旧的那个】。
//   也就是:管理员轮换了 IP、界面说"重新顶上",而订阅什么都没变。
// ─────────────────────────────────────────────────────────────────

function edAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'admin_role' => 'super', 'password' => 'a12345678']);
}

function edNode(): Node
{
    return Node::create(['name' => 'N', 'server' => '1.2.3.4', 'port' => 443, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'EDS', 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time()]);
}

it('轮换备用域名后，它成为唯一的在用域名', function () {
    $node = edNode();
    EntryDomain::create(['domain' => 'old.example.com', 'node_id' => $node->id, 'status' => 'active', 'pointed_ip' => '1.2.3.4']);
    $b = EntryDomain::create(['domain' => 'new.example.com', 'node_id' => $node->id, 'status' => 'standby']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$b->id}/rotate", ['pointed_ip' => '5.6.7.8']);

    $actives = EntryDomain::where('node_id', $node->id)->where('status', 'active')->pluck('domain')->all();
    expect($actives)->toBe(['new.example.com'], '同一节点出现了多个在用域名');
});

// `[!!]` 这一条钉住【用户看得见的后果】:订阅必须真的换成新域名。
it('轮换之后订阅发的是新域名', function () {
    $node = edNode();
    EntryDomain::create(['domain' => 'old.example.com', 'node_id' => $node->id, 'status' => 'active', 'pointed_ip' => '1.2.3.4']);
    $b = EntryDomain::create(['domain' => 'new.example.com', 'node_id' => $node->id, 'status' => 'standby']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$b->id}/rotate", ['pointed_ip' => '5.6.7.8']);

    expect($node->fresh()->entryHost())->toBe('new.example.com', '订阅仍在发旧域名 —— 轮换等于没做');
});

it('设为在用仍然只留一个', function () {
    $node = edNode();
    $a = EntryDomain::create(['domain' => 'a.example.com', 'node_id' => $node->id, 'status' => 'active']);
    $b = EntryDomain::create(['domain' => 'b.example.com', 'node_id' => $node->id, 'status' => 'standby']);

    $this->actingAs(edAdmin())->post("/admin/entry-domains/{$b->id}/activate");

    expect(EntryDomain::where('node_id', $node->id)->where('status', 'active')->pluck('domain')->all())
        ->toBe(['b.example.com']);
    expect($a->fresh()->status)->toBe('standby');
});

// `[!]` 删掉/封禁在用的那条,订阅会【静默】回退到发裸 IP(见 L-19)。
//   删除本身是合法操作(域名到期等),所以不拦 —— 但必须说出后果。
it('删掉最后一个在用域名时，提示说清会回退发裸 IP', function () {
    $node = edNode();
    $a = EntryDomain::create(['domain' => 'only.example.com', 'node_id' => $node->id, 'status' => 'active']);

    $this->actingAs(edAdmin())->delete("/admin/entry-domains/{$a->id}");

    expect((string) session('status'))->toContain('裸 IP');
    expect($node->fresh()->entryHost())->toBe('1.2.3.4', '回退口径变了');
});

it('还有别的在用域名时不误报', function () {
    $node = edNode();
    EntryDomain::create(['domain' => 'keep.example.com', 'node_id' => $node->id, 'status' => 'active']);
    $b = EntryDomain::create(['domain' => 'drop.example.com', 'node_id' => $node->id, 'status' => 'standby']);

    $this->actingAs(edAdmin())->delete("/admin/entry-domains/{$b->id}");

    expect(str_contains((string) session('status'), '裸 IP'))->toBeFalse('还有在用域名却报了回退警告');
});
