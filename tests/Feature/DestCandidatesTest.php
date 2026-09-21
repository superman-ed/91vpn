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

// `[!!]` 有两条扫描路径，而页面此前只写了一句"由该节点去扫" —— 没说清
// 第一条【不需要登录服务器】。运维会以为必须手动跑，或者压根不知道有第二条。
// 判据存在、结果能显示，都不等于运维知道怎么让它跑起来。
it('清单下方写明两条扫描路径', function () {
    $node = dcNode();
    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get("/admin/nodes/{$node->id}/edit")->assertOk()->getContent();

    expect($html)->toContain('不需要登录服务器');   // 自动那条
    expect($html)->toContain('destprobe');          // 手动那条
});

// 命令要【带上这台节点的 IP 和它当前的候选】，直接可复制 ——
// 让人自己去拼 IP 和二十几个域名，等于没给。
it('深度探测命令带上本节点的 IP 与当前候选', function () {
    $node = dcNode();
    $node->update([
        'server' => '203.0.113.9',
        'dest_scan_candidates' => "a.example.hk\nb.example.hk",
    ]);

    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get("/admin/nodes/{$node->id}/edit")->assertOk()->getContent();

    expect($html)->toContain('destprobe -vps 203.0.113.9');
    expect($html)->toContain('a.example.hk b.example.hk');
    // 二进制地址由面板自己算出来 —— 它本来就在发这个文件，不该让人去记
    expect($html)->toContain('/agent/v1/destprobe-linux-amd64');
});

// 还没填候选时给占位符，不能吐出一条缺参数的命令让人照抄
it('没有候选时命令给占位符', function () {
    $node = dcNode();
    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get("/admin/nodes/{$node->id}/edit")->assertOk()->getContent();

    expect($html)->toContain('域名1 域名2');
});

// `[!]` 一键部署的"二进制根地址"此前是空的，要运维自己填 —— 而 agent 二进制与
// install.sh 就放在本面板的 public/agent/v1，是这个面板在发它们。
// 让人去记一个面板自己知道的值，是白白制造一次出错机会。
it('部署表单预填二进制根地址', function () {
    $node = dcNode();
    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin/nodes')->assertOk()->getContent();   // 部署面板在节点列表页

    expect($html)->toContain('id="dpBase"');
    expect($html)->toContain('value="'.rtrim(url('/agent/v1'), '/').'"');
});

// `[!!]`「用最优」必须把 dest 和 server_names【一起】换掉。
//
// 早先它写成"server_names 空着才填"，而正常节点的 server_names 永远不是空的
// （现在还是必填项）—— 于是这个按钮每次都只改一半：dest 换了、SNI 留着旧的。
// 那正是我们刚加校验要挡的那种配置，结果按钮自己在生产它：管理员点一下、保存，
// 看到一个不是自己造成的错误。
it('用最优按钮把 dest 与 server_names 一起换', function () {
    $node = dcNode();
    $node->update([
        'dest_scan_at' => now(),
        'dest_scan_result' => ['scan_id' => 'x', 'results' => [
            ['host' => 'good.example', 'verdict' => 'pass', 'latency_ms' => 10,
                'tls13' => true, 'x25519' => true, 'h2' => true],
        ]],
    ]);

    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get("/admin/nodes/{$node->id}/edit")->assertOk()->getContent();

    expect($html)->toContain('id="useBest"');
    // 无条件替换。`[!]` 这里【不】用 not->toContain('!sn.value.trim()') 反着断言 ——
    //   那个字符串也出现在解释旧写法的注释里，会假阳性。正向断言已经够：
    //   改回"空着才填"的写法，下面这行就不在了。
    expect($html)->toContain('if (sn) { sn.value = h; }');
});
