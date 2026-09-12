<?php

use App\Models\Node;
use App\Models\User;
use App\Services\NodeDiagnosis;
use Illuminate\Support\Facades\DB;

/**
 * 多台落地共用同一个 dest。
 *
 * `[!!]` 主要理由是【故障爆炸半径】,不是"更难被识别":
 * dest 挂掉时用它的落地【全部同时】新连接失效(REALITY 在读 ClientHello 之前
 * 就要连上 dest,连不上直接断,密钥正确的老用户也一样)。
 * `[D]` 踩过一次:mirrors.xtom.com 当天挂掉。
 * **这一条不需要任何对手模型就成立,是可靠性问题。**
 *
 * `[!]` "共用会更易被关联识别"是 [I] 推测、未实证 —— 外部评审指出过:
 * 把它和故障隔离捆在一句话里,是把推测挂在了事实的强度上。
 * 所以用例断言的是【故障】那一半,不断言识别风险。
 */
function sdNode(string $name, string $dest, array $over = []): Node
{
    static $seq = 0;
    $seq++;

    return Node::create(array_merge([
        'name' => $name, 'server' => "10.8.0.{$seq}", 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'flow' => 'xtls-rprx-vision',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => "SD{$seq}",
        'role' => 'landing', 'enabled' => true, 'online' => true,
        'last_heartbeat' => time() - 10,
        'reality_dest' => $dest, 'reality_private_key' => 'k',
        'reality_public_key' => 'p', 'reality_short_ids' => ['ab'],
        'reality_server_names' => [explode(':', $dest)[0]],
    ], $over));
}

function sdAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('独占 dest 时 sharingDest 为空', function () {
    $a = sdNode('日本01', 'a.example:443');
    sdNode('美国01', 'b.example:443');

    expect($a->sharingDest())->toBeEmpty();
});

it('共用时列出其它几台', function () {
    $a = sdNode('日本01', 'same.example:443');
    sdNode('美国01', 'same.example:443');
    sdNode('新加坡01', 'same.example:443');

    $others = $a->sharingDest();
    expect($others)->toHaveCount(2);
    expect($others->pluck('name')->all())->toContain('美国01', '新加坡01');
});

// `[!]` 中转不跑 REALITY，它的 reality_dest 没有意义，不该算进共用。
it('中转不算进 dest 共用', function () {
    $a = sdNode('日本01', 'same.example:443');
    sdNode('香港中转', 'same.example:443', ['role' => 'relay', 'port' => 0]);

    expect($a->sharingDest())->toBeEmpty();
});

it('非 REALITY 节点不参与', function () {
    $a = sdNode('vmess 节点', '', ['type' => 'vmess', 'flow' => '',
        'reality_private_key' => null, 'reality_public_key' => null]);

    expect($a->sharingDest())->toBeEmpty();
});

it('诊断:独占报 ok,共用报 warn 并说清两重后果', function () {
    $solo = sdNode('独占的', 'solo.example:443');
    $items = collect(app(NodeDiagnosis::class)->run($solo))->keyBy('title');
    expect($items['dest 独占']['level'])->toBe('ok');

    $a = sdNode('日本01', 'shared.example:443');
    sdNode('美国01', 'shared.example:443');
    $items = collect(app(NodeDiagnosis::class)->run($a->fresh()))->keyBy('title');

    expect($items['dest 共用']['level'])->toBe('warn');
    // `[!]` 断言的是【故障爆炸半径】与【负载叠加】—— 两条都是有证据的。
    // 不断言"更易被识别":那是 [I],不该写进产品文案当结论。
    expect($items['dest 共用']['detail'])
        ->toContain('同时')->toContain('新连接全断')   // 故障域
        ->toContain('几台之和')                         // 负载叠加
        ->not->toContain('识别');                       // 推测不进文案
});

it('节点列表把 dest 撞车标出来', function () {
    sdNode('日本01', 'shared.example:443');
    sdNode('美国01', 'shared.example:443');
    sdNode('独占的', 'solo.example:443');

    $html = $this->actingAs(sdAdmin())->get('/admin/nodes')->assertOk()->getContent();

    expect($html)->toContain('dest 撞车 ×2');
    expect(substr_count($html, 'dest 撞车'))->toBe(2);   // 只有那两台，独占的不标
});

// `[!]` 与额度那一列同一个教训:视图里逐行 sharingDest() 会变成
// O(节点数) 次查询。
it('dest 共用检查不随节点数增加查询次数', function () {
    for ($i = 0; $i < 10; $i++) {
        sdNode("n{$i}", 'shared.example:443');
    }
    $admin = sdAdmin();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($admin)->get('/admin/nodes')->assertOk();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($n)->toBeLessThan(15);
});
