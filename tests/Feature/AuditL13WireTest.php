<?php

use App\Models\Node;
use App\Services\LayerHealth;
use App\Services\NodeDiagnosis;

/**
 * L-13 消费端验证：端口不在监听时，面板到底能知道什么。
 *
 * `[!!]` 这个文件的写法是被 L-00 教出来的。那次我只读了生成物（订阅 YAML）
 * 就下了结论，结果最要紧的那句是错的 —— 真客户端根本加载不了。
 * 所以这里不再从「结构体里有没有字段」推论，而是：
 *
 *   1. 让【真 agent】把节点状态发一次，抓下线上的原始字节
 *      （sogacore: internal/panel/sspanel/wirecapture_test.go，DUMP_WIRE=1 可打印）
 *   2. 把【同一份字节】交给面板的真实端点
 *   3. 看面板最终对外给出什么结论
 *
 * 第 3 步才是消费端。字段缺不缺是手段，面板说不说得出"这台不能服务"才是结论。
 */

/**
 * agent 在【最丰满】的一次上报里实际发出的全部字节。
 * REALITY 节点 + dest 探过 + 收头开着 —— 也就是面板能收到的【上界】。
 * 逐字来自 sogacore 那条测试的 WIRE 输出。
 */
const L13_WIRE = '{"load":"0.50 0.40 0.30","uptime":1234,"net_up":1000,"net_down":2000,'
    .'"accept_proxy":true,"reality_dest":"www.apple.com:443",'
    .'"reality_dest_up":true,"reality_dest_latency_ms":42}';

function l13Node(): Node
{
    static $seq = 0;
    $seq++;

    return Node::create([
        'name' => 'L13-'.$seq, 'server' => '10.13.9.'.$seq, 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'node_class' => 0, 'traffic_rate' => 1,
        'secret' => 'L13SEC'.$seq, 'role' => 'landing',
        'enabled' => true, 'online' => false, 'last_heartbeat' => 0,
    ]);
}


/**
 * 把 agent 的原始字节交给面板的真实端点。
 *
 * `[!!]` 用 $this->call() 时【必须】自己把请求头转成 server 变量 ——
 * call() 不会应用 withHeaders() 设的 defaultHeaders（只有 get/post/json 会）。
 * 第一版就是这么写错的：三条用例全收到 401，其中一条因为没断言状态码
 * 而【空真通过】—— 面板根本没收到数据，"诊断不出问题"当然成立。
 * 所以这里把 200 的断言收进辅助函数，让后面的用例不可能再空真。
 */
function l13Post(object $t, Node $node, string $body): void
{
    // `[!]` 直接写 server 变量:transformHeadersToServerVars() 是 protected。
    $server = [
        'HTTP_X_NODE_ID' => (string) $node->id,
        'HTTP_X_NODE_SECRET' => $node->secret,
        'CONTENT_TYPE' => 'application/json',
    ];
    $res = $t->call('POST', '/mod_mu/nodes/'.$node->id.'/info', [], [], [], $server, $body);
    expect($res->status())->toBe(200);
}

it('L13-1 agent 发的那份字节里，没有任何一项能表达「端口不在听」', function () {
    $wire = json_decode(L13_WIRE, true);
    expect($wire)->toBeArray();

    $suspect = array_values(array_filter(array_keys($wire), fn ($k) => (bool) preg_match(
        '/listen|ready|healthy|serving/i', $k
    )));
    // `[!]` 按字段名查而不是查值 —— 查值在字段不存在时是空真。
    expect($suspect)->toBe([]);

    // 反向对照：这条链路【有能力】携带"节点自己的姿态"，不是传不了，是没传。
    expect($wire)->toHaveKeys(['accept_proxy', 'reality_dest', 'reality_dest_up']);
});

it('L13-2 把这份字节交给真实端点，面板的结论是「在线 / 一切正常」', function () {
    $node = l13Node();

    l13Post($this, $node, L13_WIRE);

    $node->refresh();

    // 面板确实收下了、也确实更新了 —— 前置条件成立
    expect((bool) $node->online)->toBeTrue();
    expect((int) $node->last_heartbeat)->toBeGreaterThan(0);
    expect((int) $node->uptime_sec)->toBe(1234);
    expect((bool) $node->reported_accept_proxy)->toBeTrue();
    expect($node->reported_dest)->toBe('www.apple.com:443');

    // `[!!]` 而此刻节点的端口可以是完全不接受连接的 ——
    // agent 那边 Health() 已经 dial 过、已经写了 "core reports not listening"、
    // core_listening 指标也落到 0。面板这边:
    $layers = app(LayerHealth::class)->forNode($node);
    expect($layers['relay']['state'])->toBe('ok');          // 「节点」层:绿
    expect($layers['dest']['state'])->toBe('na');           // 非 REALITY 配置,不适用
    expect(app(LayerHealth::class)->worst($layers))->toBe('ok');

    // 节点列表照样显示在线
    expect((bool) $node->fresh()->online)->toBeTrue();
});

it('L13-3 连节点诊断页也说不出问题 —— 它能拿到的证据就是那 8 个字段', function () {
    $node = l13Node();
    l13Post($this, $node, L13_WIRE);

    // 前置条件：面板确实收下了这次上报。
    // 没有这一行，"诊断不出问题"可以靠 401 而空真成立 —— 第一版就栽在这里。
    expect((bool) $node->fresh()->online)->toBeTrue();

    $d = app(NodeDiagnosis::class)->run($node->fresh());
    $bad = collect($d['checks'] ?? [])->filter(fn ($c) => ($c['state'] ?? '') === 'bad');

    // 没有任何一项检查会因为"端口不在听"而变红 —— 面板没有这个信息
    expect($bad->pluck('name')->all())->toBe([]);
});

it('L13-4 对照：心跳【停了】面板是知道的 —— 缺的只是「活着但不能服务」这一档', function () {
    $node = l13Node();
    l13Post($this, $node, L13_WIRE);
    expect(app(LayerHealth::class)->forNode($node->fresh())['relay']['state'])->toBe('ok');

    // 把心跳拨老（agent 停了 / 内核没起来导致 push 提前返回，见 P12-A）
    $node->update(['last_heartbeat' => time() - 400]);
    expect(app(LayerHealth::class)->forNode($node->fresh())['relay']['state'])->toBe('bad');

    // `[!]` 有这条对照，上面几条才说明问题：
    // 不是"面板对节点一无所知"，是【只有心跳这一个维度】——
    // 而"进程还在、端口不听"恰好在这个维度上是绿的。
});
