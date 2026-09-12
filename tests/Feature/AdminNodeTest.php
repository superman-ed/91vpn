<?php

use App\Models\Node;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('lists nodes', function () {
    Node::create(['name' => '香港01', 'server' => 'hk.x.com', 'port' => 100, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    $this->actingAs($this->admin)->get('/admin/nodes')->assertOk()->assertSee('香港01');
});

it('creates a node with auto-generated secret', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => '日本01', 'server' => 'jp.x.com', 'port' => 10086,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1.5, 'node_class' => 2,
    ])->assertRedirect('/admin/nodes');

    $node = Node::where('name', '日本01')->first();
    expect($node)->not->toBeNull();
    expect($node->node_class)->toBe(2);
    expect(strlen($node->secret))->toBeGreaterThanOrEqual(16);
});

it('updates a node', function () {
    $node = Node::create(['name' => '旧名', 'server' => 'x.com', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    $this->actingAs($this->admin)->put("/admin/nodes/{$node->id}", [
        'name' => '新名', 'server' => 'y.com', 'port' => 2, 'type' => 'vmess', 'net' => 'ws', 'traffic_rate' => 2, 'node_class' => 1,
    ])->assertRedirect('/admin/nodes');
    expect($node->fresh()->name)->toBe('新名');
    expect($node->fresh()->net)->toBe('ws');
});

it('deletes a node', function () {
    $node = Node::create(['name' => 'del', 'server' => 'x.com', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    $this->actingAs($this->admin)->delete("/admin/nodes/{$node->id}")->assertRedirect('/admin/nodes');
    expect(Node::find($node->id))->toBeNull();
});

it('blocks normal user from node admin', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->post('/admin/nodes', ['name' => 'x'])->assertForbidden();
});

// `[!!]` vision 组合服务端硬拦(和前端 check() 同口径)——前端只提醒,绕过表单
// 直接 POST 仍能存下坏组合,而 vision 选错是"装完才连不上"那种难查的失败。
it('拒绝 vless+vision 但传输不是 tcp（ws）', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-ws', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'ws', 'flow' => 'xtls-rprx-vision',
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('flow');
    expect(Node::where('name', 'bad-ws')->exists())->toBeFalse();
});

it('拒绝 vless+vision 但既没 TLS 也没 REALITY', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-bare', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'flow' => 'xtls-rprx-vision',
        'tls' => 0, 'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('flow');
    expect(Node::where('name', 'bad-bare')->exists())->toBeFalse();
});

it('允许 vless+vision+tcp+tls 的合法组合', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'ok-vision', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'flow' => 'xtls-rprx-vision',
        'tls' => 1, 'traffic_rate' => 1, 'node_class' => 0,
    ])->assertRedirect('/admin/nodes');
    expect(Node::where('name', 'ok-vision')->first()?->flow)->toBe('xtls-rprx-vision');
});
