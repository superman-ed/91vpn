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
// P13-A 没有可用节点时，订阅把【全部流量】静默送去直连
// ─────────────────────────────────────────────────────────────────
it('P13-A 有节点时，兜底流量走代理', function () {
    p13Node('日本01');
    expect(p13ResolveMatch(p13Yaml(apiUser())))->toBe('日本01');
});

it('P13-A2 一个节点都拿不到时，兜底流量走 DIRECT —— 客户端照常显示已连接', function () {
    p13Node('高级节点', class: 5);        // apiUser 等级不够，看不到它
    $u = apiUser();

    $y = p13Yaml($u);

    // 前置条件：账号是可用的，订阅正常生成（没有抛异常），只是没有节点
    expect($y['proxies'])->toBe([]);
    // 自动组按设计整个去掉了 —— 这一段是对的，配置确实是可加载的
    expect(collect($y['proxy-groups'])->pluck('name'))
        ->not->toContain('自动选择')->not->toContain('故障转移');

    // `[!!]` 而这里是要害：Proxy 组回落成 ['DIRECT']，
    // 于是 MATCH → 其他 → Proxy → DIRECT。
    // 用户导入成功、客户端显示"已连接"、每一个字节都没走代理。
    expect(p13ResolveMatch($y))->toBe('DIRECT');
});

it('P13-A3 这与 P0-2 实测确立的判据直接冲突', function () {
    // lab/relay-failover-probe.sh [D]-4 的判据原话：
    //   「关键不是"失败"，是【不能静默直连】。直连也能成功,
    //     那意味着用户以为在用代理、实际在裸奔」
    // 那条判据管的是"两个中转都挂了"，而本例是"一个节点都没有"——
    // 用户侧的表现【完全相同】，结论却相反：探针要求失败，订阅给了直连。
    p13Node('高级节点', class: 5);
    $y = p13Yaml(apiUser());

    $groups = collect($y['proxy-groups'])->keyBy('name');
    expect($groups['Proxy']['proxies'])->toBe(['DIRECT']);
    // 若哪天改成 REJECT（可加载但流量失败、且组名说得出原因），这条会红。
    expect($groups['Proxy']['proxies'])->not->toBe(['REJECT']);
});

it('P13-A4 不止兜底：连显式指向 Proxy 的规则也一起走了直连', function () {
    p13Node('高级节点', class: 5);
    $y = p13Yaml(apiUser());
    $groups = collect($y['proxy-groups'])->keyBy('name');

    // 挑一条明确写着要走代理的规则（模板里 openai.com → OpenAI 组）
    $openai = collect($y['rules'])->first(fn ($r) => str_contains($r, 'openai.com'));
    expect($openai)->not->toBeNull();
    expect($groups['OpenAI']['proxies'][0])->toBe('Proxy');
    expect($groups['Proxy']['proxies'][0])->toBe('DIRECT');
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
// P13-C 可达路径：全网中断 → 付费用户刷新订阅 → 拿到一份「全部直连」
// ─────────────────────────────────────────────────────────────────
it('P13-C 全部节点离线时，付费用户刷新订阅拿到的是全直连配置', function () {
    $u = apiUser();                       // 有效会员
    $n1 = p13Node('日本01');
    $n2 = p13Node('美国01');
    expect(p13ResolveMatch(p13Yaml($u)))->toBe('日本01');   // 正常时走代理

    // 机房/上游出事，nodes:mark-offline 把两台都置离线
    Node::whereIn('id', [$n1->id, $n2->id])->update(['online' => false]);

    // `[!!]` 用户此刻【恰恰会去刷新订阅】—— 因为"连不上了"，
    // 而各家客户端的第一条建议就是"更新订阅试试"。
    $y = p13Yaml($u);

    expect($y['proxies'])->toBe([]);
    expect(p13ResolveMatch($y))->toBe('DIRECT');

    // 账号本身完全正常，订阅接口也没报错 —— 没有任何一处会告诉用户出了什么事
    expect($u->fresh()->banned)->toBeFalse();
});
