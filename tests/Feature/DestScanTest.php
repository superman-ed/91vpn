<?php

use App\Models\Node;
use App\Models\User;

/**
 * dest 候选筛查的面板半边：下发候选 → 节点上扫 → 收结果。
 *
 * [!!] 筛查在【节点】上跑，面板只编排：可达与延迟是"这台机器到那个站"的关系，
 * 而 REALITY 握手时是落地去连 dest（sogacore compatibility/dest-scan.md §1）。
 *
 * 这组用例守三件事：幂等键会随内容变、晚到的旧结果不会覆盖新一轮、
 * 换了候选清单必须把旧结果清掉。
 */
function scanNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vless', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'NODESECRET',
    ], $attr));
}

it('只在配了候选时才下发 dest_scan', function () {
    $node = scanNode();
    $cc = $this->getJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET")
        ->assertOk()->json('data.custom_config');
    expect($cc)->not->toHaveKey('dest_scan');

    $node->update([
        'dest_scan_candidates' => "www.a.example\nwww.b.example",
        'dest_scan_id' => Node::destScanIdFor(['www.a.example', 'www.b.example']),
    ]);
    $cc = $this->getJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET")->json('data.custom_config');
    expect($cc['dest_scan']['candidates'])->toBe(['www.a.example', 'www.b.example']);
    expect($cc['dest_scan']['id'])->toBe($node->dest_scan_id);
});

// [!!] 幂等键必须只随内容变：面板每个拉取周期都重发同一份 nodeInfo，
// id 每次都变的话，节点会一直重扫，对第三方站点就是持续的可疑流量。
it('候选内容不变时幂等键不变，顺序无关', function () {
    $a = Node::destScanIdFor(['b.example', 'a.example']);
    $b = Node::destScanIdFor(['a.example', 'b.example']);
    expect($a)->toBe($b);
    expect(Node::destScanIdFor(['a.example']))->not->toBe($a);
});

it('收下与当前 scan_id 匹配的结果', function () {
    $node = scanNode([
        'dest_scan_candidates' => 'www.a.example',
        'dest_scan_id' => 'abc123',
    ]);
    $this->postJson("/mod_mu/nodes/{$node->id}/dest_scan?key=NODESECRET", [
        'scan_id' => 'abc123', 'at' => time(),
        'results' => [['host' => 'www.a.example', 'verdict' => 'pass', 'tls13' => true,
            'x25519' => true, 'h2' => true, 'key_group' => 'X25519', 'latency_ms' => 42]],
    ])->assertOk()->assertJson(['ret' => 1]);

    $node->refresh();
    expect($node->dest_scan_result['results'][0]['verdict'])->toBe('pass');
    expect($node->dest_scan_at)->not->toBeNull();
});

// [!!] 晚到的旧结果必须丢掉：运维刚换了候选清单，页面上却被上一轮覆盖，
// 而两份结果长得一模一样 —— 看不出自己看的是过期的。
it('丢掉 scan_id 对不上的结果', function () {
    $node = scanNode(['dest_scan_candidates' => 'www.a.example', 'dest_scan_id' => 'new-id']);
    $this->postJson("/mod_mu/nodes/{$node->id}/dest_scan?key=NODESECRET", [
        'scan_id' => 'old-id', 'results' => [['host' => 'x', 'verdict' => 'pass']],
    ])->assertOk();

    expect($node->refresh()->dest_scan_result)->toBeNull();
});

it('结果条数封顶，不让节点决定我们存多少', function () {
    $node = scanNode(['dest_scan_id' => 'cap']);
    $many = array_fill(0, 80, ['host' => 'x.example', 'verdict' => 'pass']);
    $this->postJson("/mod_mu/nodes/{$node->id}/dest_scan?key=NODESECRET",
        ['scan_id' => 'cap', 'results' => $many])->assertOk();

    expect($node->refresh()->dest_scan_result['results'])->toHaveCount(50);
});

// 换了候选清单 → 换 id → 旧结果必须清掉（否则运维以为新清单已经扫完了）
it('后台改候选清单会换 id 并清掉旧结果', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $node = scanNode([
        'dest_scan_candidates' => 'www.a.example',
        'dest_scan_id' => Node::destScanIdFor(['www.a.example']),
        'dest_scan_result' => ['scan_id' => 'x', 'results' => [['host' => 'www.a.example']]],
        'dest_scan_at' => now(),
    ]);

    $this->actingAs($admin)->put("/admin/nodes/{$node->id}", [
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vless', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0,
        'dest_scan_candidates' => "www.a.example\nwww.c.example",
    ])->assertRedirect();

    $node->refresh();
    expect($node->dest_scan_id)->toBe(Node::destScanIdFor(['www.a.example', 'www.c.example']));
    expect($node->dest_scan_result)->toBeNull();
    expect($node->dest_scan_at)->toBeNull();
});

it('清单没变时强制重扫会换 id', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $old = Node::destScanIdFor(['www.a.example']);
    $node = scanNode(['dest_scan_candidates' => 'www.a.example', 'dest_scan_id' => $old]);

    $this->actingAs($admin)->put("/admin/nodes/{$node->id}", [
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vless', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0,
        'dest_scan_candidates' => 'www.a.example', 'dest_scan_rerun' => 1,
    ])->assertRedirect();

    expect($node->refresh()->dest_scan_id)->not->toBe($old);
    expect($node->dest_scan_id)->toStartWith($old.'-');
});
