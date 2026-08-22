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
