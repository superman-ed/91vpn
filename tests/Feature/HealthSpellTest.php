<?php

use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\NodeHealthSpell;
use App\Models\RuleOutboundStatus;
use App\Services\HealthSampler;
use Illuminate\Support\Carbon;

/**
 * 存活区段的采集。
 *
 * `[!!]` 这组用例守的主要是【统计口径】，不是代码分支：
 *   - 人工下线是删失，不是失效；
 *   - 观察开始时已经活着的节点要标左截断；
 *   - 一次抖动不算一次死亡；
 *   - 失效时刻取第一次失败采样，不是检出时刻。
 * 口径错了，代码再对，结论也是错的。
 */
$GLOBALS['hsSeq'] = 0;

function hsNode(array $over = []): Node
{
    return Node::create(array_merge([
        'name' => 'N'.(++$GLOBALS['hsSeq']), 'server' => '203.0.113.'.$GLOBALS['hsSeq'],
        'port' => 39500, 'type' => 'vless', 'net' => 'tcp', 'traffic_rate' => 1,
        'node_class' => 0, 'secret' => 'HS'.$GLOBALS['hsSeq'], 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

/**
 * `[!!]` 注入桩探测器：不让测试去打真实网络。
 * 不注入时会去连 203.0.113.x（TEST-NET，不可路由），每次卡满 3 秒超时。
 * 默认返回 false（探不通）—— 对应"机器没了"，是更常见的那种失效。
 */
function hsSample(?Carbon $at = null, bool $portOpen = false): array
{
    $s = new HealthSampler(app(\App\Services\LayerHealth::class),
        fn (string $h, int $p) => $portOpen);

    return $s->sample($at ?? now());
}

function hsSpell(Node $n): ?NodeHealthSpell
{
    return NodeHealthSpell::where('node_id', $n->id)->latest('id')->first();
}

it('观察开始时已经活着的节点要标左截断', function () {
    // `[!!]` 不标的话，这些区段的存活时间会被系统性低估 ——
    // 真实起点在观察窗口之前，且不可知。
    $n = hsNode();                       // 有心跳 = 我们看它之前它就活着
    hsSample();

    expect(hsSpell($n)->left_truncated)->toBeTrue();
});

it('从未有过心跳的节点第一次健康时，不是左截断', function () {
    $n = hsNode(['last_heartbeat' => 0]);
    hsSample();
    // 没有心跳 → 判不可用，连区段都不该开
    expect(hsSpell($n))->toBeNull();
});

it('人工停用是【删失】，不是失效', function () {
    // `[!!]` 算成失效等于把运维动作混进"环境把它弄死了"里 ——
    // 而那正是我们要测的东西。
    $n = hsNode();
    hsSample(now());
    $n->update(['enabled' => false]);
    hsSample(now()->addMinutes(5));
    hsSample(now()->addMinutes(10));

    $s = hsSpell($n);
    expect($s->outcome)->toBe('censored')
        ->and($s->reason)->toBe('manual');
});

it('节点被删掉时按删失结束，不留悬空区段', function () {
    $n = hsNode();
    hsSample();
    $id = $n->id;
    $n->delete();
    hsSample(now()->addMinutes(5));

    $s = NodeHealthSpell::where('node_id', $id)->first();
    expect($s->outcome)->toBe('censored')->and($s->reason)->toBe('deleted');
});

it('一次抖动不算一次死亡', function () {
    $n = hsNode();
    hsSample(now());
    $n->update(['online' => false, 'last_heartbeat' => time() - 9999]);
    hsSample(now()->addMinutes(5));      // 只失败一次

    expect(hsSpell($n)->ended_at)->toBeNull();
});

it('抖动后恢复的，仍是同一段 —— 不该被切成两截', function () {
    // `[!]` 切成两截会让"存活时间"被系统性切碎，
    // 分布的尾部（长寿节点）会凭空消失。
    $n = hsNode();
    hsSample(now());
    $n->update(['online' => false, 'last_heartbeat' => time() - 9999]);
    hsSample(now()->addMinutes(5));
    $n->update(['online' => true, 'last_heartbeat' => time()]);
    hsSample(now()->addMinutes(10));

    expect(NodeHealthSpell::where('node_id', $n->id)->count())->toBe(1);
    $s = hsSpell($n);
    expect($s->ended_at)->toBeNull()
        ->and($s->misses)->toBe(0)
        ->and($s->first_miss_at)->toBeNull();
});

it('失效时刻取第一次失败采样，不是检出时刻', function () {
    // `[!!]` 真实失效发生在两次采样之间。取检出时刻会系统性高估存活时间 ——
    // 每个区段都多算一个采样周期。
    $t0 = Carbon::parse('2026-09-13 12:00:00');
    $n = hsNode();
    hsSample($t0);
    $n->update(['online' => false, 'last_heartbeat' => time() - 9999]);
    hsSample($t0->copy()->addMinutes(5));     // 第一次失败
    hsSample($t0->copy()->addMinutes(10));    // 第二次 → 结束

    expect(hsSpell($n)->ended_at->format('H:i'))->toBe('12:05');
});

it('dest 挂掉的落地不算存活 —— 端口在听但没人能握手', function () {
    $n = hsNode([
        'reality_private_key' => 'k', 'reality_dest' => 'x.example:443',
        'reported_dest' => 'x.example:443', 'reported_dest_up' => false,
        'reported_dest_failures' => 5, 'dest_reported_at' => now(),
    ]);
    hsSample(now());
    hsSample(now()->addMinutes(5));

    expect(hsSpell($n))->toBeNull();   // 从来没进入过可用状态
});

it('劣化（变慢）仍算存活 —— 它确实还在服务', function () {
    // `[!]` 把劣化算成失效，"存活时间"就变成了"没有劣化的时间"，那是另一个量。
    $n = hsNode([
        'reality_private_key' => 'k', 'reality_dest' => 'x.example:443',
        'reported_dest' => 'x.example:443', 'reported_dest_up' => true,
        'reported_dest_latency_ms' => 420, 'reported_dest_degraded' => true,
        'dest_reported_at' => now(),
    ]);
    hsSample();

    expect(hsSpell($n))->not->toBeNull();
});

it('判定里含"未知"的采样要单独计数', function () {
    // `[!!]` 中转所在规则没开健康检查时，「到落地」永远是 unknown ——
    // 那种区段的"存活"是【推定】的，不是观测到的。
    // 不分开记，一堆推定值会混进结论里。
    $relay = hsNode(['role' => 'relay', 'port' => 0]);
    $rule = ForwardRule::create([
        'name' => 'nohc', 'enabled' => true, 'listen_port' => '47001',
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => false,
    ]);
    RuleOutboundStatus::create([
        'rule_id' => $rule->id, 'node_id' => $relay->id, 'tag' => 'o1',
        'dial' => '9.9.9.9:443', 'backup' => false, 'alive' => true, 'live' => 0,
        'reported_at' => now(),
    ]);
    hsSample();

    $s = hsSpell($relay);
    expect($s->observations)->toBe(1)->and($s->unknown_observations)->toBe(1);
});

it('角色按区段开始时快照 —— 改了 role 不该追溯性改变归属', function () {
    $n = hsNode(['role' => 'relay', 'port' => 0]);
    hsSample();
    $n->update(['role' => 'landing']);
    hsSample(now()->addMinutes(5));

    expect(hsSpell($n)->role)->toBe('relay');
});

it('未结束与删失的区段【同样贡献暴露时间】', function () {
    // `[!!]` 漏掉它们就是"只数死掉的、不数活着的"，失效率会被高估到没有意义。
    $t0 = Carbon::parse('2026-09-13 12:00:00');
    $n = hsNode();
    hsSample($t0);
    hsSample($t0->copy()->addHours(24));

    expect(hsSpell($n)->exposureSeconds())->toBe(86400);
});

/**
 * 心跳没了之后，区分"机器没了"与"只有 agent 进程没了"。
 *
 * `[!!]` 这个区分只在【面板本来就连得上那个端口】时成立。
 * accept_proxy 的落地按设计只对中转放行，面板连不过去是【正常的】——
 * 拿它推断"机器没了"会得到一个稳定错误的结论，而且永远不会自己暴露。
 */
function hsKill(Node $n, Carbon $t0, bool $portOpen): NodeHealthSpell
{
    hsSample($t0, $portOpen);
    $n->update(['online' => false, 'last_heartbeat' => time() - 9999]);
    hsSample($t0->copy()->addMinutes(5), $portOpen);
    hsSample($t0->copy()->addMinutes(10), $portOpen);

    return hsSpell($n->fresh());
}

it('心跳没了但端口还通 → agent 进程没了', function () {
    $s = hsKill(hsNode(), Carbon::parse('2026-09-13 12:00:00'), portOpen: true);
    expect($s->outcome)->toBe('failed')->and($s->reason)->toBe('agent_gone');
});

it('心跳没了且端口也连不上 → 机器或网络没了', function () {
    $s = hsKill(hsNode(), Carbon::parse('2026-09-13 12:00:00'), portOpen: false);
    expect($s->outcome)->toBe('failed')->and($s->reason)->toBe('unreachable');
});

it('accept_proxy 落地判不出原因时报 unknown，不硬猜', function () {
    // 面板连不上它【是设计如此】。拿这个"连不上"推断"机器没了"，
    // 会得到一个稳定错误、且永远不自我暴露的结论。
    $n = hsNode(['accept_proxy_protocol' => true]);
    $s = hsKill($n, Carbon::parse('2026-09-13 12:00:00'), portOpen: false);

    expect($s->outcome)->toBe('failed')->and($s->reason)->toBe('unknown');
});

// ─────────────────────────────────────────────────────────────────
// 审计发现 P12-C（钉住当前行为，不是期望行为）
//
// 存活统计分不清「节点活着」和「没人在看」。
// 这两件事在数据上长得一模一样，而它们对结论的含义正相反：
// 前者是分母(暴露时间)该算的，后者根本不该算。
// ─────────────────────────────────────────────────────────────────
it('P12-C 采样中断的那段时间被整段算成「存活」', function () {
    Carbon::setTestNow($t0 = Carbon::parse('2026-09-01 00:00:00'));
    $n = hsNode();

    // 第一次采样：开区段
    hsSample($t0);
    expect(hsSpell($n))->not->toBeNull();

    // 采样停了 6 小时（调度容器挂了 / 数据库不可达 / 部署窗口），
    // 期间【没有任何观察】。恢复后节点照样健康。
    $t1 = $t0->copy()->addHours(6);
    Carbon::setTestNow($t1);
    $n->update(['last_heartbeat' => time() - 5]);   // [!] 心跳判定用真实时钟,不受 setTestNow 影响
    hsSample($t1);

    $spell = hsSpell($n);
    // 暴露时间按【墙上时钟】算，6 小时一秒不少地进了分母
    expect($spell->exposureSeconds())->toBe(6 * 3600);
    // 而这 6 小时里真正看过的次数是 0 —— 全程只有首尾两次
    expect((int) $spell->observations)->toBe(2);

    // `[!!]` 判据在这里：按 5 分钟一采，6 小时本该有 73 次观察。
    // 数据【足以】发现这件事(observations 就在表里)，
    // 但 exposureSeconds() 不看它,health:spells 也不比对 ——
    // 于是「6 小时无人观察」和「6 小时持续健康」在报表上完全相同。
    $expected = intdiv($spell->exposureSeconds(), 300) + 1;
    expect($expected)->toBe(73);
    expect((int) $spell->observations)->toBeLessThan($expected);

    Carbon::setTestNow();
});

it('P12-C2 对照：正常采样时观察次数与暴露时间是对得上的', function () {
    Carbon::setTestNow($t = Carbon::parse('2026-09-01 00:00:00'));
    $n = hsNode();

    for ($i = 0; $i < 7; $i++) {
        Carbon::setTestNow($at = $t->copy()->addMinutes(5 * $i));
        $n->update(['last_heartbeat' => time() - 5]);
        hsSample($at);
    }

    $spell = hsSpell($n);
    expect((int) $spell->observations)->toBe(7);
    expect($spell->exposureSeconds())->toBe(30 * 60);
    // 没有缺口时两者自洽 —— 说明上一条测到的差距确实来自"没人看"，
    // 不是这个口径本身就对不上。
    expect(intdiv($spell->exposureSeconds(), 300) + 1)->toBe(7);

    Carbon::setTestNow();
});
