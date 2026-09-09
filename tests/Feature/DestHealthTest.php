<?php

use App\Models\Node;

/**
 * 节点回报的 REALITY dest 探活。
 *
 * [!!] dest 失效是【静默】的：借用的第三方站点挂掉或被套 CDN 后，节点照常启动、
 * 端口照常监听、面板显示"在线"，而没有任何客户端能完成握手。
 * agent 一直在探（sogacore bfd8740），面板此前把它丢掉了 —— 这组用例守住那条链路。
 */
function healthNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vless', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'NODESECRET',
    ], $attr));
}

it('收下节点回报的 dest 探活', function () {
    $node = healthNode();
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", [
        'uptime' => 1, 'load' => '0.1',
        'reality_dest' => 'www.a.example:443', 'reality_dest_up' => false, 'reality_dest_failures' => 3,
    ])->assertOk();

    $node->refresh();
    expect($node->reported_dest)->toBe('www.a.example:443');
    expect($node->reported_dest_up)->toBeFalse();
    expect($node->reported_dest_failures)->toBe(3);
    expect($node->destHealth())->toBe('down');
});

// [!] 旧 agent 与非 reality 节点都不带这几个键 —— 必须保持 null（从没报过），
// 不能写成 false，否则面板会把"这台不跑 reality"显示成"dest 挂了"。
it('没带这几个键时保持"从没报过"', function () {
    $node = healthNode();
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", ['uptime' => 1])->assertOk();

    $node->refresh();
    expect($node->reported_dest_up)->toBeNull();
    expect($node->destHealth())->toBe('unknown');
});

// [!!] 陈旧一律按 unknown：一台停机的节点，它最后一次上报的 true 会永远留在库里 ——
// 不判过期就等于把"节点死了"渲染成"dest 是好的"。
it('过期的上报按未知处理，不按正常', function () {
    $node = healthNode([
        'reported_dest' => 'www.a.example:443', 'reported_dest_up' => true,
        'dest_reported_at' => now()->subHour(),
    ]);
    expect($node->destHealth())->toBe('unknown');

    $node->update(['dest_reported_at' => now()]);
    expect($node->fresh()->destHealth())->toBe('ok');
});
