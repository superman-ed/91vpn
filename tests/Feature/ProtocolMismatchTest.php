<?php

use App\Models\Node;
use App\Models\User;

/**
 * 节点回报它【实际在跑】的协议，面板据此发现"配的"与"跑的"不一致。
 *
 * `[!!]` 协议不来自面板 —— sspanel/mod_mu 的契约是节点从本机 agent.conf 读
 * server_type（soga、XrayR 同样如此），下发的 nodeInfo 里根本没有这一项。
 * 于是在后台把协议 vmess 改成 vless：保存成功、订阅立刻改口发 vless+reality，
 * 而节点【继续跑 vmess】—— 它每轮 pull 都因 ErrRealityNeedsVLESS 失败，
 * 但那只是节点自己日志里的一行 WARN，面板这边心跳照常、在线标着绿的。
 *
 * `[D]` 实测踩中：外部 TLS 握手零响应，排查十四分钟才找到是
 * /etc/agent/agent.conf 里的 server_type 没跟着改。这组用例守住那条链路。
 */
function pmNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'NODESECRET',
    ], $attr));
}

it('收下节点回报的 server_type', function () {
    $node = pmNode(['type' => 'vless']);
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", [
        'uptime' => 1, 'server_type' => 'vmess',
    ])->assertOk();

    $node->refresh();
    expect($node->reported_server_type)->toBe('vmess');
    expect($node->server_type_reported_at)->not->toBeNull();
});

// `[!]` 旧 agent 不带这个键 —— 必须保持 null（从没报过）。写成空串的话，
// "没报过"会看起来像"报了个空值"，而两者的处置完全不同。
it('没带 server_type 时保持从没报过', function () {
    $node = pmNode();
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", ['uptime' => 1])->assertOk();

    $node->refresh();
    expect($node->reported_server_type)->toBeNull();
    expect($node->protocolMismatch())->toBeFalse();   // 没报过 ≠ 不一致
});

it('报的和配的不一致时判为不一致', function () {
    $node = pmNode(['type' => 'vless']);
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", [
        'uptime' => 1, 'server_type' => 'vmess',
    ])->assertOk();

    expect($node->fresh()->protocolMismatch())->toBeTrue();
});

it('报的和配的一致时不报警', function () {
    $node = pmNode(['type' => 'vless']);
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", [
        'uptime' => 1, 'server_type' => 'vless',
    ])->assertOk();

    expect($node->fresh()->protocolMismatch())->toBeFalse();
});

// `[!!]` 判据存在还不够 —— 不摆到管理员看得见的地方，等于没有。
// 今天卡住的十四分钟里，面板上【没有任何一处】能看出协议不符。
it('节点列表把实际在跑的协议摆出来', function () {
    $node = pmNode(['name' => '香港测试', 'type' => 'vless']);
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", [
        'uptime' => 1, 'server_type' => 'vmess',
    ])->assertOk();

    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin/nodes')->assertOk()->getContent();

    expect($html)->toContain('实际在跑 VMESS');
    // 提示里要写清楚【去哪改】—— 面板改不动它，这是全文最容易踩空的一点
    expect($html)->toContain('agent.conf');
});

it('一致时列表页不出现这个徽章', function () {
    $node = pmNode(['type' => 'vless']);
    $this->postJson("/mod_mu/nodes/{$node->id}/info?key=NODESECRET", [
        'uptime' => 1, 'server_type' => 'vless',
    ])->assertOk();

    $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin/nodes')->assertOk()->getContent();

    expect($html)->not->toContain('实际在跑');
});
