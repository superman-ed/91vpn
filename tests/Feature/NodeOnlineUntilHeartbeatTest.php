<?php

use App\Models\Node;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────
// `[!!]` nodes.online 以前默认 true,而后台节点表单【没有 online 字段】——
//   于是在后台点一下「新增节点」,新节点就带着 online=1 / enabled=1 /
//   last_heartbeat=0 落库,而订阅筛的是 `online=1 AND enabled=1`,
//   所以它【立刻进入所有人的订阅】,声称在线而从未上报过。
//
// `[D]` 2026-09-24 实际发生:19 个 server 指向 .placeholder.invalid 的占位节点
//   进了订阅(v2rayNG 拿到 20 个,19 个连不上),还把落地页「通航地区」
//   从 1 个吹到 13 个(地区是从启用节点的名字识别的)。
//
//   默认值改成 false 后,online 这个字段在每个时点都是诚实的:
//     建节点           → 0,不进订阅(哪怕 enabled=1)
//     agent 首次上报   → 1,进订阅
//     失联 180s        → nodes:mark-offline 置 0,出订阅
// ─────────────────────────────────────────────────────────────────

/** 造一个没指定 online 的节点 —— 模拟"后台刚点了新增,机器还没装 agent"。 */
function freshNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => '新买的机器', 'server' => 'new.example.com', 'port' => 30001,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1,
        'node_class' => 0, 'secret' => 'NEWSECRET',
    ], $attr));
}

function hbSubUser(): User
{
    return User::factory()->create([
        'invite_token' => 'HBTOKEN', 'class' => 2, 'class_expire' => now()->addDays(10),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 0, 'd' => 0,
    ]);
}

it('新建节点在 agent 上报前不算在线', function () {
    $node = freshNode();

    expect($node->fresh()->online)->toBeFalse();
    expect((int) $node->fresh()->last_heartbeat)->toBe(0);
    // enabled 仍然默认开 —— 它是运维开关,与"是否上报过"是两件事
    expect($node->fresh()->enabled)->toBeTrue();
});

// `[!!]` 这是本组最要紧的一条:它钉住的是【用户看得见的后果】,
//   而不只是一个字段的取值。
it('从未上报的节点即使 enabled 也不会出现在任何人的订阅里', function () {
    hbSubUser();
    $node = freshNode(['enabled' => true]);

    $res = $this->get('/sub/HBTOKEN')->assertOk();

    expect($res->getContent())->not->toContain('新买的机器');
    expect($res->getContent())->not->toContain($node->server);
});

it('agent 真的上报之后，节点才进订阅', function () {
    hbSubUser();
    $node = freshNode(['enabled' => true]);

    // 先确认此刻不在
    $this->get('/sub/HBTOKEN')->assertOk()->assertDontSee('新买的机器');

    // 走真实心跳端点,不是手改字段
    $this->get("/mod_mu/func/ping?node_id={$node->id}&key=NEWSECRET")->assertOk();

    expect($node->fresh()->online)->toBeTrue();
    expect((int) $node->fresh()->last_heartbeat)->toBeGreaterThan(0);

    $this->get('/sub/HBTOKEN')->assertOk()->assertSee('新买的机器');
});

// `[!]` 与 NodeOfflineAndPruneTest 的分工:那组测的是"曾上线又失联"的节点
//   会被 nodes:mark-offline 置离线;这组测的是"从未上线"的节点【一开始就不在线】。
//   两者合起来保证 online 在整个生命周期都不撒谎。
/** 只取「通航地区」那一块 —— 页面别处的静态文案也会提到国家名,不能整页搜。 */
function regionsBlock(string $html): string
{
    return preg_match('#<section id="regions".*?</section>#s', $html, $m) ? $m[0] : '';
}

it('落地页的通航地区不会把没上报过的节点算进去', function () {
    // 一个真上报过的(香港)+ 一个从未上报的(日本)
    $hk = freshNode(['name' => '香港 01', 'enabled' => true, 'secret' => 'HK1']);
    $this->get("/mod_mu/func/ping?node_id={$hk->id}&key=HK1")->assertOk();
    freshNode(['name' => '日本 东京 01', 'enabled' => true, 'port' => 30002, 'secret' => 'JP1']);

    $block = regionsBlock($this->get('/')->assertOk()->getContent());

    expect($block)->not->toBe('');
    expect($block)->toContain('香港');        // 上报过 → 算
    expect($block)->not->toContain('日本');   // 从未上报 → 不算
    expect($block)->toContain('DESTINATIONS · 1');
});

// `[!!]` 此前识别不到地区时会用 FALLBACK_REGIONS 顶上 6 个地区名 ——
//   一台节点都没有也宣称覆盖香港/日本/新加坡/美国/台湾/韩国。那是编数据。
it('一个上报过的节点都没有时，整块隐掉而不是编出 6 个地区', function () {
    freshNode(['enabled' => true]);   // 有节点,但从未上报

    $html = $this->get('/')->assertOk()->getContent();

    expect(regionsBlock($html))->toBe('');       // 整块不渲染
    expect($html)->not->toContain('DESTINATIONS');
});
