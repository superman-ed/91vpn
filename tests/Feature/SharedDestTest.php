<?php

use App\Models\Node;
use App\Models\User;
use App\Services\NodeDiagnosis;
use Illuminate\Support\Facades\DB;

/**
 * 多台落地共用同一个 dest。
 *
 * `[!!]` 两重后果，独立且不同：
 * ① **关联风险**：我们要求 dest 非 CDN，而非 CDN 的站正常只有一两个 IP。
 *    十台落地都声称自己是同一个站，本身就是异常模式 ——
 *    识别或封禁其中一台，就顺藤摸到全部。
 * ② **负载叠加**：每条用户新连接都要连一次 dest（严格 1:1，
 *    见 sogacore compatibility/dest-latency.md），共用时它承受的是几台之和。
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
    expect($items['dest 共用']['detail'])
        ->toContain('识别一台就顺藤摸到全部')   // ① 关联风险
        ->toContain('几台之和');                 // ② 负载叠加
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
