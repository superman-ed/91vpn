<?php

use App\Models\Node;
use App\Models\User;
use App\Services\DestCandidates;

/**
 * REALITY dest 候选生成。
 *
 * `[!!]` 判据本身要能被断言，所以纯筛选（shortlist）与 DNS／随机采样是分开的：
 * 混在一起就只能靠跑真实网络来验，而那种测试既慢又不稳。
 *
 * 这组用例也【钉住两个已知剔不掉的类别】—— 不是遗漏，是过滤方式本身够不着。
 * 钉住它们，是为了以后没人"顺手修好"之后反而把正常候选误伤了。
 */
function dcFake(string $csv): void
{
    $p = sys_get_temp_dir().'/dc-test-'.getmypid().'.csv';
    file_put_contents($p, $csv);
    config(['services.dest_candidates.ranking_path' => $p]);
}

// 排名：母域用来判"是不是全球品牌的地区站"，地区站本身要落在中段
const DC_CSV = <<<'CSV'
59,yahoo.com
589,tripadvisor.com
11755,dbs.com
40228,xtom.com
1000,toohot.hk
25000,yahoo.com.hk
30000,tripadvisor.com.hk
35000,dbs.com.hk
40000,xtom.com.hk
45000,localshop.com.hk
50000,hongkongpost.hk
60000,foo.gov.hk
500000,toocold.hk
CSV;

it('剔掉全球品牌的地区站', function () {
    dcFake(DC_CSV);
    $l = app(DestCandidates::class)->shortlist('.hk');

    // 母域排名 59 / 589 —— 全球品牌，它们的流量本就不会从一台小 VPS 出来
    expect($l)->not->toContain('yahoo.com.hk');
    expect($l)->not->toContain('tripadvisor.com.hk');
});

it('剔掉 .gov 的', function () {
    dcFake(DC_CSV);
    expect(app(DestCandidates::class)->shortlist('.hk'))->not->toContain('foo.gov.hk');
});

it('只取排名中段', function () {
    dcFake(DC_CSV);
    $l = app(DestCandidates::class)->shortlist('.hk');

    expect($l)->not->toContain('toohot.hk');    // 排名 1000：推荐列表都有它，是靶子
    expect($l)->not->toContain('toocold.hk');   // 排名 50 万：长尾，多是死站或内部系统
});

it('保留正常的本地站', function () {
    dcFake(DC_CSV);
    $l = app(DestCandidates::class)->shortlist('.hk');

    expect($l)->toContain('localshop.com.hk');
    expect($l)->toContain('xtom.com.hk');       // 母域排 40228，不算全球品牌
});

// `[!!]` 已知局限一：金融机构剔不掉。
// 反直觉但确凿 —— 银行的全球母域排名【反而很低】（dbs.com 11755、zurich.com 24017、
// aia.com 206540），因为客户都去本地站。按母域排名过滤够不着它们；
// 把阈值放宽到能抓住 dbs.com，就会连 xtom.com（40228）这种正常候选一起误伤。
// 所以这一条【故意】留给人工，UI 上写明。
it('已知剔不掉：银行的地区站会留在清单里', function () {
    dcFake(DC_CSV);
    $l = app(DestCandidates::class)->shortlist('.hk');

    expect($l)->toContain('dbs.com.hk');
    // 同时钉住"为什么不能简单放宽阈值"：放宽会误伤它
    expect($l)->toContain('xtom.com.hk');
});

// `[!!]` 已知局限二：不在 .gov 这个 TLD 下的公营机构剔不掉。
// 实跑里漏出过香港邮政、香港电台。同样留给人工。
it('已知剔不掉：不在 .gov 下的公营机构会留在清单里', function () {
    dcFake(DC_CSV);
    expect(app(DestCandidates::class)->shortlist('.hk'))->toContain('hongkongpost.hk');
});

// ── 面板这一侧 ──────────────────────────────────────────────────

function dcNode(): Node
{
    return Node::create([
        'name' => 'dc', 'server' => 's', 'port' => 443, 'type' => 'vless', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'DCSECRET',
    ]);
}

it('生成后填入清单，并清掉上一轮结果', function () {
    $node = dcNode();
    $node->update(['dest_scan_result' => ['scan_id' => 'old', 'results' => [['host' => 'a.example']]], 'dest_scan_at' => now()]);

    $this->mock(DestCandidates::class, fn ($m) => $m->shouldReceive('generate')
        ->andReturn(['candidates' => ['a.example.hk', 'b.example.hk'], 'stats' => []]));

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post("/admin/nodes/{$node->id}/dest-candidates")->assertRedirect();

    $node->refresh();
    expect($node->dest_scan_candidates)->toBe("a.example.hk\nb.example.hk");
    expect($node->dest_scan_id)->not->toBeNull();
    // `[!]` 旧结果必须清掉：两轮结果长得一模一样，留着会让人以为新清单已经扫完了
    expect($node->dest_scan_result)->toBeNull();
    expect($node->dest_scan_at)->toBeNull();
});

it('普通用户打不动', function () {
    $node = dcNode();
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->post("/admin/nodes/{$node->id}/dest-candidates")->assertForbidden();
});

// `[!!]` 判据存在还不够 —— 两个剔不掉的类别必须【写在管理员看得见的地方】，
// 否则他会以为清单已经干净了，直接拿去用。
it('页面把人工要复核的几件事写出来', function () {
    $node = dcNode();
    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get("/admin/nodes/{$node->id}/edit")->assertOk()->getContent();

    expect($html)->toContain('自动生成候选');
    expect($html)->toContain('金融');       // 剔不掉的类别一
    expect($html)->toContain('公营机构');    // 剔不掉的类别二
    expect($html)->toContain('自然度');
});

// `[!!]` 判定名本身说不出"为什么不合格"。redirect 这一关尤其:管理员要看的是
// 【跳去哪】—— 跳自己 www 子域是官方允许的,跳到别人家才是跳转器。
// 只显示 "redirect" 的话,人工复核还得回节点上再查一遍。
it('结果表里显示跳转去了哪', function () {
    $node = dcNode();
    $node->update([
        'dest_scan_at' => now(),
        'dest_scan_result' => ['scan_id' => 'x', 'results' => [
            ['host' => 'shortener.example', 'verdict' => 'redirect',
                'redirect' => true, 'redirect_to' => 'somewhere-else.example'],
        ]],
    ]);

    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get("/admin/nodes/{$node->id}/edit")->assertOk()->getContent();

    expect($html)->toContain('redirect');
    expect($html)->toContain('跳转到 somewhere-else.example');
});
