<?php

use App\Models\Node;
use App\Services\SubscriptionService;
use Symfony\Component\Yaml\Yaml;

/**
 * Clash 订阅的自动切换分组。
 *
 * `[!!]` 它解决的是【恢复速度】，不是被封概率 —— 这两件事要分开：
 * 中转是用户直连的那一跳，它挂了没有任何服务端组件能替用户改道
 * （面板只能"不再发给下次拉订阅的人"，而客户端默认 24 小时才更新）。
 * 所以中转的"自动摘除"最终只能靠客户端。
 */
function cgNode(string $name, int $class = 0): Node
{
    static $seq = 0;
    $seq++;

    return Node::create([
        'name' => $name, 'server' => "10.7.0.{$seq}", 'port' => 443,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1,
        'node_class' => $class, 'secret' => "CG{$seq}", 'role' => 'landing',
        'enabled' => true, 'online' => true,
    ]);
}

/**
 * 生成一次订阅并解析。
 *
 * `[!!]` 用例【自己传用户】,不要在这里 apiUser():
 *   · 每次调用都新建用户 → 两次 cgYaml() 拿到两份不同的配置
 *   · 改用 static 缓存又会【跨用例残留】(数据库每个用例回滚,静态变量不回滚)
 * 两个坑我都踩了。让调用方持有用户,是唯一不会出错的写法。
 */
function cgYaml(\App\Models\User $u): array
{
    return Yaml::parse(app(SubscriptionService::class)->generateClash($u->fresh()));
}

it('有节点时生成自动选择与故障转移两个组', function () {
    cgNode('日本01');
    cgNode('美国01');

    $y = cgYaml(apiUser());                       // `[!]` 只生成一次再复用
    $g = collect($y['proxy-groups'])->keyBy('name');

    expect($g)->toHaveKey('自动选择')->toHaveKey('故障转移');
    expect($g['自动选择']['type'])->toBe('url-test');
    expect($g['故障转移']['type'])->toBe('fallback');
    // `[!]` 订阅条目名【不一定等于节点名】:挂在中转后面时会带入口后缀
    // (「日本01 · 香港中转」,见 entrypoints())。所以对着 proxies 里的实际名字断言。
    expect($g['自动选择']['proxies'])->toBe(collect($y['proxies'])->pluck('name')->all());
});

// `[!!]` 客户端默认选中 select 组的【第一项】。自动组排在最前 =
// 什么都不做的用户得到的是会自愈的那条。
it('Proxy 组的第一项是自动选择', function () {
    cgNode('日本01');

    $y = cgYaml(apiUser());
    $g = collect($y['proxy-groups'])->keyBy('name');

    expect($g['Proxy']['type'])->toBe('select');
    expect($g['Proxy']['proxies'][0])->toBe('自动选择');
    expect($g['Proxy']['proxies'][1])->toBe('故障转移');
    $first = collect($y['proxies'])->pluck('name')->first();
    expect($g['Proxy']['proxies'])->toContain($first);   // 手动钉住某个节点仍然可选
});

// `[!!]` 这条是边界:proxies 为空的 url-test 组会让【整份配置加载失败】,
// 而用户看到的只是"订阅导入失败",查不到原因。
// 这种情况真实存在:新用户等级 0 而所有节点都设了门槛。
it('没有可用节点时,自动组整个去掉而不是填 DIRECT', function () {
    cgNode('高级节点', class: 5);   // apiUser 的 class=1，看不到它

    $y = cgYaml(apiUser());
    $names = collect($y['proxy-groups'])->pluck('name');

    expect($names)->not->toContain('自动选择')->not->toContain('故障转移');
    // Proxy 组仍在，且回落到 DIRECT —— 配置本身仍然是可加载的
    $proxy = collect($y['proxy-groups'])->firstWhere('name', 'Proxy');
    expect($proxy['proxies'])->toBe(['DIRECT']);
});

// `[!]` 探测间隔不能太密:每次探测都是给节点(以及它背后的 dest)添连接。
it('自动组的探测间隔与容差是克制的', function () {
    cgNode('日本01');

    $g = collect(cgYaml(apiUser())['proxy-groups'])->keyBy('name');


    expect($g['自动选择']['interval'])->toBeGreaterThanOrEqual(120);
    expect($g['自动选择']['lazy'])->toBeTrue();        // 没流量时不测
    expect($g['自动选择']['tolerance'])->toBeGreaterThan(0);  // 免得在相近节点间来回抖
});

it('生成的 YAML 是合法的且所有组引用得到', function () {
    cgNode('日本01');
    cgNode('美国01');

    $y = cgYaml(apiUser());
    $known = collect($y['proxies'])->pluck('name')
        ->merge(collect($y['proxy-groups'])->pluck('name'))
        ->merge(['DIRECT', 'REJECT'])->all();

    // `[!!]` toContain($x, $msg) 的第二个参数【不是自定义消息】,
    // Pest 会把它当成【另一个要查找的元素】—— 于是断言在数组里找那句中文,
    // 失败信息写着"引用了不存在的 日本01",而日本01 明明在里面。
    // 我为此查了半天功能代码,实际是误用 API。要带上下文就自己收集再断言。
    $missing = [];
    foreach ($y['proxy-groups'] as $grp) {
        foreach ($grp['proxies'] ?? [] as $ref) {
            if (! in_array($ref, $known, true)) {
                $missing[] = "{$grp['name']} → {$ref}";
            }
        }
    }
    expect($missing)->toBe([]);
});
