<?php

use App\Models\Node;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('saves ws path/host/tls on node', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'HK-WS', 'server' => 'hk.example.com', 'port' => 443, 'type' => 'vmess',
        'net' => 'ws', 'path' => '/vpn', 'host' => 'cdn.example.com', 'tls' => 1,
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertRedirect('/admin/nodes');

    $n = Node::where('name', 'HK-WS')->first();
    expect($n->net)->toBe('ws');
    expect($n->path)->toBe('/vpn');
    expect($n->host)->toBe('cdn.example.com');
    expect($n->tls)->toBeTrue();
    expect($n->secret)->not->toBeEmpty();
});

it('clash config carries ws-opts and tls for ws node', function () {
    $user = User::factory()->create(['invite_token' => 'WSTOKEN', 'class' => 1, 'class_expire' => now()->addDays(10), 'transfer_enable' => 100 * 1024 ** 3]);
    Node::create(['name' => 'WS01', 'server' => 'ws.example.com', 'port' => 443, 'type' => 'vmess', 'net' => 'ws', 'path' => '/ray', 'host' => 'cdn.io', 'tls' => true, 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);

    $res = $this->get('/sub/WSTOKEN?flag=clash')->assertOk();
    $res->assertSee('/ray')->assertSee('cdn.io');
});

it('regenerates node secret', function () {
    $node = Node::create(['name' => 'N', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'oldsecret']);

    $this->actingAs($this->admin)->post("/admin/nodes/{$node->id}/regenerate-secret")->assertRedirect();
    expect($node->fresh()->secret)->not->toBe('oldsecret');
});
