<?php

use App\Models\Node;
use App\Models\User;
use App\Services\SubscriptionService;

function activeUserWithNode(): User
{
    $u = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDay(),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 0, 'd' => 0,
        'uuid' => '11111111-2222-3333-4444-555555555555',
    ]);
    Node::create(['name' => '香港01', 'server' => 'hk.example.com', 'port' => 12345, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's', 'online' => true]);
    return $u;
}

it('generates v2rayn base64 with vmess:// lines', function () {
    $u = activeUserWithNode();
    $out = app(SubscriptionService::class)->generate($u, 'v2ray');
    $decoded = base64_decode($out);
    expect($decoded)->toContain('vmess://');
    // 每条 vmess:// 后是 base64(JSON)，解出应含服务器和uuid
    $line = trim(explode("\n", $decoded)[0]);
    $json = json_decode(base64_decode(substr($line, strlen('vmess://'))), true);
    expect($json['add'])->toBe('hk.example.com');
    expect($json['id'])->toBe($u->uuid);
});

it('generates clash yaml', function () {
    $u = activeUserWithNode();
    $out = app(SubscriptionService::class)->generate($u, 'clash');
    expect($out)->toContain('proxies:');
    expect($out)->toContain($u->uuid);
});

it('generates generic base64 (ss/v2ray mixed list)', function () {
    $u = activeUserWithNode();
    $out = app(SubscriptionService::class)->generate($u, 'sub');
    expect(base64_decode($out, true))->not->toBeFalse();
    expect(base64_decode($out))->toContain('vmess://');
});

it('defaults to clash for unknown flag', function () {
    $u = activeUserWithNode();
    $out = app(SubscriptionService::class)->generate($u, 'whatever');
    expect($out)->toContain('proxies:');
});
