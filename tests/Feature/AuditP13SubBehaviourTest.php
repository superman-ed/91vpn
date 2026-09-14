<?php

use App\Models\Node;
use App\Services\SubscriptionService;
use Symfony\Component\Yaml\Yaml;

/**
 * 审计实验 P1-3 订阅 → 客户端实际行为 —— 只做观察，不修复。
 *
 * 审的是【面板发出去的东西，客户端真的按面板以为的方式在跑吗】。
 *
 * `[!!]` 判据必须顺着【客户端的规则链】走，不能只看面板生成了什么组。
 * 「Proxy 组存在」和「流量真的走代理」之间隔着 rules → 组 → 成员三层，
 * 只断言第一层会漏掉本文件里最重要的那条。
 */
function p13Node(string $name, int $class = 0): Node
{
    static $seq = 0;
    $seq++;

    return Node::create([
        'name' => $name, 'server' => "10.13.0.{$seq}", 'port' => 443,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1,
        'node_class' => $class, 'secret' => "P13{$seq}", 'role' => 'landing',
        'enabled' => true, 'online' => true,
    ]);
}

function p13Yaml(\App\Models\User $u): array
{
    return Yaml::parse(app(SubscriptionService::class)->generateClash($u->fresh()));
}

/**
 * 顺着规则链，解出「一条不匹配任何域名规则的普通流量」最终走哪。
 *
 * 这正是客户端做的事：MATCH 兜底 → 落到某个组 → 取该组默认项（第一个成员）
 * → 若默认项还是组就继续往下，直到落在一个真正的出口上。
 */
function p13ResolveMatch(array $y): string
{
    $groups = collect($y['proxy-groups'])->keyBy('name');
    $last = collect($y['rules'])->last();
    expect($last)->toStartWith('MATCH,');
    $cur = substr($last, strlen('MATCH,'));

    for ($i = 0; $i < 10; $i++) {
        if (! $groups->has($cur)) {
            return $cur;                       // 落到 DIRECT / REJECT / 某个节点名
        }
        $cur = $groups[$cur]['proxies'][0];     // select 的默认项 = 第一个成员
    }
    throw new RuntimeException('组引用成环');
}

// ─────────────────────────────────────────────────────────────────
// P13-A 拿不到节点时，订阅必须让流量【失败】，不能静默直连
//
// `[!!]` 这一组原本是审计实验，断言的是缺陷本身（回落到 DIRECT）。
// 缺陷已修（SubscriptionService::NO_NODE_GROUP），断言随之反转，
// 用例保留下来当回归守卫 —— 这条路回不去了。
// ─────────────────────────────────────────────────────────────────
it('P13-A 有节点时，兜底流量走代理', function () {
    p13Node('日本01');
    expect(p13ResolveMatch(p13Yaml(apiUser())))->toBe('日本01');
});

it('P13-A2 一个节点都拿不到时，兜底流量走 REJECT —— 不是 DIRECT', function () {
    p13Node('高级节点', class: 5);        // apiUser 等级不够，看不到它
    $u = apiUser();

    $y = p13Yaml($u);

    // 前置条件：账号是可用的，订阅正常生成（没有抛异常），只是没有节点
    expect($y['proxies'])->toBe([]);
    // 自动组仍然按原样整个去掉 —— 空 url-test 会让配置加载失败，那个坑不能回来
    expect(collect($y['proxy-groups'])->pluck('name'))
        ->not->toContain('自动选择')->not->toContain('故障转移');

    // MATCH → 其他 → Proxy → 无可用节点 → REJECT
    expect(p13ResolveMatch($y))->toBe('REJECT');
});

it('P13-A3 判据与 P0-2 实测确立的那条一致了', function () {
    // lab/relay-failover-probe.sh [D]-4 的判据原话：
    //   「关键不是"失败"，是【不能静默直连】。直连也能成功,
    //     那意味着用户以为在用代理、实际在裸奔」
    // 那条管的是"两个中转都挂了"，本例是"一个节点都没有"——
    // 用户侧表现完全相同，所以判据必须相同。
    p13Node('高级节点', class: 5);
    $y = p13Yaml(apiUser());

    $groups = collect($y['proxy-groups'])->keyBy('name');
    expect($groups['Proxy']['proxies'])->toBe([SubscriptionService::NO_NODE_GROUP]);
    expect($groups['Proxy']['proxies'])->not->toBe(['DIRECT']);
});

it('P13-A4 显式指向 Proxy 的规则同样失败，而不是偷偷直连', function () {
    p13Node('高级节点', class: 5);
    $y = p13Yaml(apiUser());
    $groups = collect($y['proxy-groups'])->keyBy('name');

    // 挑一条明确写着要走代理的规则（模板里 openai.com → OpenAI 组）
    $openai = collect($y['rules'])->first(fn ($r) => str_contains($r, 'openai.com'));
    expect($openai)->not->toBeNull();
    expect($groups['OpenAI']['proxies'][0])->toBe('Proxy');
    expect($groups['Proxy']['proxies'][0])->toBe(SubscriptionService::NO_NODE_GROUP);
    expect($groups[SubscriptionService::NO_NODE_GROUP]['proxies'])->toBe(['REJECT']);
});

it('P13-A5 该走直连的仍然走直连 —— 修复没有把国内流量也一起打掉', function () {
    p13Node('高级节点', class: 5);
    $y = p13Yaml(apiUser());
    $groups = collect($y['proxy-groups'])->keyBy('name');

    // `[!!]` 这条是修复的【爆炸半径】守卫。国内 / Steam / 亚洲流媒体
    // 本来就默认走 DIRECT，那是正确行为，不该被这次改动波及。
    foreach (['国内', 'Steam', '亚洲流媒体'] as $g) {
        expect($groups[$g]['proxies'][0])->toBe('DIRECT');
    }
    // 而这些规则确实还在，指向的就是那几个组
    expect(collect($y['rules'])->last())->toBe('MATCH,其他');
    expect(collect($y['rules'])->contains('GEOIP,CN,国内'))->toBeTrue();
});

it('P13-A6 无节点时配置仍然可加载：没有空组，所有引用解得开', function () {
    p13Node('高级节点', class: 5);
    $y = p13Yaml(apiUser());

    $known = collect($y['proxies'])->pluck('name')
        ->merge(collect($y['proxy-groups'])->pluck('name'))
        ->merge(['DIRECT', 'REJECT'])->all();

    $bad = [];
    foreach ($y['proxy-groups'] as $g) {
        if (($g['proxies'] ?? []) === []) {
            $bad[] = "组「{$g['name']}」成员为空";
        }
        foreach ($g['proxies'] ?? [] as $m) {
            if (! in_array($m, $known, true)) {
                $bad[] = "组「{$g['name']}」引用了不存在的「{$m}」";
            }
        }
    }
    foreach ($y['rules'] as $r) {
        $target = last(explode(',', $r));
        if (! in_array($target, $known, true) && ! str_starts_with($target, 'no-resolve')) {
            $bad[] = "规则「{$r}」指向不存在的「{$target}」";
        }
    }
    // `[!]` 收集完再断言 —— toContain($x, $msg) 的第二个参数不是消息，
    // 本项目为此踩过两次（判据 74）。
    expect($bad)->toBe([]);
});

// ─────────────────────────────────────────────────────────────────
// P13-B 自动故障转移【只有 Clash 用户有】
// ─────────────────────────────────────────────────────────────────
it('P13-B v2rayN / base64 格式里没有任何自动切换机制', function () {
    p13Node('日本01');
    p13Node('美国01');
    $u = apiUser();
    $svc = app(SubscriptionService::class);

    $v2 = base64_decode($svc->generateV2rayN($u->fresh()));
    $b64 = base64_decode($svc->generateBase64($u->fresh()));

    // 两种格式都只是【一串平铺的节点链接】，没有组、没有探测 URL、没有 interval
    foreach (['v2rayN' => $v2, 'base64' => $b64] as $fmt => $body) {
        expect($body)->not->toContain('url-test', "格式 {$fmt}");
        expect(substr_count($body, "\n") + 1)->toBeGreaterThanOrEqual(2);
    }

    // 对照：Clash 有
    expect(collect(p13Yaml($u)['proxy-groups'])->pluck('type'))->toContain('url-test');

    // `[!]` 这不是缺陷,是格式本身的能力差别。记在这里是因为
    // P0-1「客户端自动故障转移」的结论【只对 Clash 用户成立】——
    // 而下载页对两类客户端是并列推荐的,没有说明这个差别。
});

// ─────────────────────────────────────────────────────────────────
// P13-C 可达路径：全网中断 → 付费用户刷新订阅
// ─────────────────────────────────────────────────────────────────
it('P13-C 全部节点离线时，付费用户刷新订阅拿到的是【会失败】的配置', function () {
    $u = apiUser();                       // 有效会员
    $n1 = p13Node('日本01');
    $n2 = p13Node('美国01');
    expect(p13ResolveMatch(p13Yaml($u)))->toBe('日本01');   // 正常时走代理

    // 机房/上游出事，nodes:mark-offline 把两台都置离线
    Node::whereIn('id', [$n1->id, $n2->id])->update(['online' => false]);

    // `[!!]` 用户此刻【恰恰会去刷新订阅】—— 因为"连不上了"，
    // 而各家客户端的第一条建议就是"更新订阅试试"。
    // 这一刷，以前会把他变成裸奔，现在是明确失败。
    $y = p13Yaml($u);

    expect($y['proxies'])->toBe([]);
    expect(p13ResolveMatch($y))->toBe('REJECT');
    // 组名本身就是解释 —— 客户端的组列表里读得到
    expect(collect($y['proxy-groups'])->pluck('name'))
        ->toContain(SubscriptionService::NO_NODE_GROUP);
});

// ─────────────────────────────────────────────────────────────────
// P13-D 空 proxies 必须是序列，不能是空 map
// ─────────────────────────────────────────────────────────────────
it('P13-D 没有节点时 proxies 渲染成 [] 而不是 {  }', function () {
    p13Node('高级节点', class: 5);
    $yaml = app(SubscriptionService::class)->generateClash(apiUser()->fresh());

    // `[!!]` 断言必须打在【YAML 文本】上，不能打在解析后的数组上。
    // `proxies: {  }` 和 `proxies: []` 解析成 PHP 都是空数组 —— 一模一样。
    // 本文件此前所有用例走的都是 Yaml::parse() 之后的结构，
    // 所以它们【全部】看不见这个缺陷，而它会让 mihomo 在第 12 行直接 fatal：
    //   Parse config error: cannot unmarshal !!map into []map[string]interface{}
    // 也就是整份订阅根本加载不起来。
    // `[D]` 2026-09-14 用真 mihomo v1.19.14 实测确认。
    expect($yaml)->toContain("\nproxies: []\n");
    expect($yaml)->not->toContain('proxies: {');
});

it('P13-D2 有节点时 proxies 仍然是正常的序列', function () {
    p13Node('日本01');
    $yaml = app(SubscriptionService::class)->generateClash(apiUser()->fresh());

    expect($yaml)->not->toContain('proxies: {');
    expect($yaml)->toContain('日本01');
    // 反向对照：确认这一条不是靠"没有 proxies 键"而假通过
    expect($yaml)->toContain("\nproxies:\n");
});
