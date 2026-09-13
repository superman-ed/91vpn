<?php

use App\Models\Node;
use App\Models\RuleOutboundStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 「到落地」这一跳要在节点列表上【看得见】。
 *
 * `[!!]` 逐个点进去诊断才发现的话，没人会发现 —— 与 dest 撞车那一列同一个理由。
 * 2026-09-13 实测的形态：中转心跳 19 秒前、端口在听、列表显示"在线"，
 * 而它到落地 8 秒超时无回包，订阅照发给用户。
 */
function rhvAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

$GLOBALS['rhvSeq'] = 0;

function rhvRelay(array $over = []): Node
{
    return Node::create(array_merge([
        'name' => '中转', 'server' => '203.0.113.'.(++$GLOBALS['rhvSeq']), 'port' => 0,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'RH'.$GLOBALS['rhvSeq'], 'role' => 'relay',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 19,
    ], $over));
}

function rhvHop(Node $relay, bool $alive, $at = 'now'): void
{
    static $seq = 0;
    // `[!]` 必须挂在开了健康检查的规则上 —— 没开的话 alive 恒为真,
    // 面板按无证据处理(见 LayerHealthTest 里那两条用例)。
    $rule = \App\Models\ForwardRule::create([
        'name' => 'hc'.$seq, 'enabled' => true, 'listen_port' => (string) (41000 + $seq),
        'inbound_node_set' => [], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
    RuleOutboundStatus::create([
        'rule_id' => $rule->id, 'node_id' => $relay->id, 'tag' => 'fwd-out-1-'.($seq++),
        'dial' => '179.253.249.78:39500', 'backup' => false, 'alive' => $alive,
        'live' => 0, 'reported_at' => $at === 'now' ? now() : $at,
    ]);
}

it('到落地不通的中转，在列表上直接标出来', function () {
    $r = rhvRelay();
    rhvHop($r, false);

    $this->actingAs(rhvAdmin())->get('/admin/nodes')
        ->assertOk()
        ->assertSee('到落地不通')
        // 必须说清楚这一跳面板测不了 —— 否则人会去 ping 落地再据此下结论。
        ->assertSee('面板测不了', false);
});

it('上报过期的中转标成「?」而不是什么都不标', function () {
    $r = rhvRelay();
    rhvHop($r, true, now()->subHours(7));   // 停机 7 小时,最后一次报的是"通"

    $this->actingAs(rhvAdmin())->get('/admin/nodes')
        ->assertOk()->assertSee('到落地 ?');
});

it('一切正常的中转不加任何标记', function () {
    $r = rhvRelay();
    rhvHop($r, true);

    $html = $this->actingAs(rhvAdmin())->get('/admin/nodes')->assertOk()->getContent();
    expect($html)->not->toContain('到落地不通')->not->toContain('到落地 ?');
});

// `[!]` 这一列的判定读的是关联表。不预加载就是 O(节点数) 次查询 ——
// 额度那一列实测过 5 → 29，dest 撞车那一列也栽过同一个坑。
it('这一列不随节点数增加查询次数', function () {
    for ($i = 0; $i < 10; $i++) {
        rhvHop(rhvRelay(), true);
    }
    $admin = rhvAdmin();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($admin)->get('/admin/nodes')->assertOk();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($n)->toBeLessThan(15);
});

it('到落地变慢的中转，在列表上标成黄的而不是绿的', function () {
    $r = rhvRelay();
    rhvHop($r, true);
    \App\Models\RuleOutboundStatus::where('node_id', $r->id)->update(['delay_ms' => 60, 'slow' => true]);

    $this->actingAs(rhvAdmin())->get('/admin/nodes')
        ->assertOk()->assertSee('到落地变慢')->assertDontSee('到落地不通');
});
