<?php

use App\Models\Node;
use App\Models\User;

/**
 * L-15 消费端验证：后台「今日流量 / 每节点流量」那个数字准不准。
 *
 * `[!!]` 这个数字的用途是【跟机房账单对账】。所以消费端不是 node_daily_traffic
 * 这张表，而是后台页面上渲染出来的那一串字 —— 之前的 P11-B 实验只验到表，
 * 没验到人真正会去看的地方。
 */
function l15Node(float $rate = 1.0, int $minClass = 0): Node
{
    static $seq = 0;
    $seq++;

    return Node::create([
        'name' => 'L15-'.$seq, 'server' => '10.15.0.'.$seq, 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'node_class' => $minClass, 'traffic_rate' => $rate,
        'secret' => 'L15SEC'.$seq, 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ]);
}

/** agent 上报流量时实际发出的字节形状（sspanel 适配器：{"data":[{user_id,u,d}]}）。 */
function l15Report(object $t, Node $node, array $rows): void
{
    $server = [
        'HTTP_X_NODE_ID' => (string) $node->id,
        'HTTP_X_NODE_SECRET' => $node->secret,
        'CONTENT_TYPE' => 'application/json',
    ];
    $res = $t->call('POST', '/mod_mu/users/traffic', [], [], [], $server,
        json_encode(['data' => $rows]));
    expect($res->status())->toBe(200);
}

it('L15-1 后台「今日流量」少算了被拒收用户的那部分', function () {
    $node = l15Node(minClass: 3);              // 节点要求 class >= 3
    $ok = User::factory()->create(['class' => 3]);
    $banned = User::factory()->create(['class' => 3, 'banned' => true]);
    $lowClass = User::factory()->create(['class' => 0]);

    // 节点【真实转发】的总量：3,000 + 10,000 + 14,000 = 27,000 字节
    l15Report($this, $node, [
        ['user_id' => $ok->id, 'u' => 1_000, 'd' => 2_000],
        ['user_id' => $banned->id, 'u' => 5_000, 'd' => 5_000],
        ['user_id' => $lowClass->id, 'u' => 7_000, 'd' => 7_000],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $html = $this->get('/admin')->assertOk()->getContent();

    // `[!!]` 消费端:后台首页那个数字。human_bytes(3000) = "2.93 KB"
    expect($html)->toContain(human_bytes(3_000));
    expect($html)->not->toContain(human_bytes(27_000));
});

it('L15-2 节点列表页的每节点流量列，同样只有被接受的那部分', function () {
    $node = l15Node(minClass: 3);
    $ok = User::factory()->create(['class' => 3]);
    $banned = User::factory()->create(['class' => 3, 'banned' => true]);

    l15Report($this, $node, [
        ['user_id' => $ok->id, 'u' => 1_000, 'd' => 2_000],
        ['user_id' => $banned->id, 'u' => 100_000, 'd' => 100_000],
    ]);

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $html = $this->get('/admin/nodes')->assertOk()->getContent();

    expect($html)->toContain($node->name);
    expect($html)->toContain(human_bytes(3_000));
    // 20 万字节真的过了这台机器的网卡，页面上一个字都没有
    expect($html)->not->toContain(human_bytes(203_000));
});

it('L15-3 而且是静默的：页面上没有任何"有数据被丢弃"的提示', function () {
    $node = l15Node(minClass: 3);
    $lowClass = User::factory()->create(['class' => 0]);

    l15Report($this, $node, [['user_id' => $lowClass->id, 'u' => 9_999, 'd' => 0]]);

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $html = $this->get('/admin/nodes')->assertOk()->getContent();

    foreach (['丢弃', '拒收', '未计入', '部分'] as $word) {
        expect($html)->not->toContain($word.'流量');
    }
    // 数据库里也没有任何计数可供事后查证
    expect(\DB::table('node_daily_traffic')->where('node_id', $node->id)->count())->toBe(0);
});

it('L15-4 对照：全部用户都合规时，页面上的数字与节点实际转发量一致', function () {
    $node = l15Node(minClass: 0);
    $a = User::factory()->create(['class' => 0]);
    $b = User::factory()->create(['class' => 0]);

    l15Report($this, $node, [
        ['user_id' => $a->id, 'u' => 1_000, 'd' => 2_000],
        ['user_id' => $b->id, 'u' => 3_000, 'd' => 4_000],
    ]);

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $html = $this->get('/admin')->assertOk()->getContent();

    // `[!]` 有这条对照，上面三条才说明问题：
    // 不是"这个数字一直是错的"，是【只在有拒收时】悄悄变小。
    expect($html)->toContain(human_bytes(10_000));
});
