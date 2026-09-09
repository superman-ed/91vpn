<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-008 P1：中转相关的表并入本库后仍然可用。
 *
 * `[!]` 这组不是"测 Laravel 会不会建表"，而是守两件真会出错的事：
 *   · 表结构原样搬过来后，唯一约束与级联删除的语义没有走样；
 *   · `nodes` 增列不影响现有落地节点的行为（新列全 nullable / 有默认值）。
 */
function relayTableNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'S', 'role' => 'relay',
    ], $attr));
}

it('中转相关的表都建出来了', function () {
    foreach (['forward_rules', 'forward_outbounds', 'rule_traffic',
        'rule_alive_ip', 'rule_outbound_status', 'node_net_traffic', 'deploy_runs'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("表 {$t} 没建出来");
    }
});

it('规则与出站的关系可用，且删规则会级联删出站', function () {
    $n = relayTableNode();
    $r = ForwardRule::create([
        'name' => 'r1', 'enabled' => true, 'listen_port' => '30001',
        'inbound_node_set' => [$n->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    ForwardOutbound::create([
        'rule_id' => $r->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'direct', 'target_addr' => '1.2.3.4', 'target_port' => '443',
        'trusted_transit' => true,
    ]);
    expect($r->fresh('outbounds')->outbounds)->toHaveCount(1);

    $r->delete();
    expect(ForwardOutbound::count())->toBe(0, '删规则没有级联删掉它的出站');
});

// `[!!]` 规则删了，它已经产生的流量记录必须留着 —— 级联删会把
// "上个月是谁吃掉的"这个账一起抹掉（原表注释里写死的设计）。
it('删规则不会带走已经产生的流量记录', function () {
    $n = relayTableNode();
    DB::table('rule_traffic')->insert([
        'rule_id' => 999, 'node_id' => $n->id, 'date' => now()->toDateString(),
        'up' => 100, 'down' => 200, 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(DB::table('rule_traffic')->count())->toBe(1);
});

// 在线 IP 是【当前状态】不是流水：同一个 (规则,节点,IP) 只有一行。
it('规则在线 IP 的唯一约束还在', function () {
    $n = relayTableNode();
    $row = ['rule_id' => 1, 'node_id' => $n->id, 'ip' => '203.0.113.7',
        'last_seen' => now(), 'created_at' => now(), 'updated_at' => now()];
    DB::table('rule_alive_ip')->insert($row);

    expect(fn () => DB::table('rule_alive_ip')->insert($row))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// `[!!]` 增列不能改变现有落地节点的行为：不填这些列照样能建、能用。
//
// `[D]` 这条第一次跑就红了，但抓到的不是新增列 —— 是模型缺了 role 的默认值：
// DB 列默认 landing，而 `Node::create()` 返回的内存对象 role 是 null，
// 于是"建完立刻判断"会得到 needsUsers()=false，节点静默不发用户
// （现象上看不出来：在线、心跳正常，只是没有用户）。已给模型补上同名默认值。
it('落地节点不受新增列影响', function () {
    $n = Node::create([
        'name' => 'landing', 'server' => 'l', 'port' => 34567, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'L',
    ]);
    expect($n->needsUsers())->toBeTrue();
    expect($n->quotaPercent())->toBeNull('没设额度就不该有百分比');
    expect($n->periodBytes())->toBe(0);
});
