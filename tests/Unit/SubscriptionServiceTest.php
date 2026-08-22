<?php

use App\Models\Node;
use App\Models\User;
use App\Services\SubscriptionService;
use Symfony\Component\Yaml\Yaml;

function makeActiveUser(int $class = 2): User
{
    return User::factory()->create([
        'class' => $class,
        'class_expire' => now()->addDays(10),
        'transfer_enable' => 100 * 1024 ** 3,
        'u' => 0, 'd' => 0,
    ]);
}

it('generates clash yaml with nodes the user can access', function () {
    $user = makeActiveUser(class: 2);
    Node::create(['name' => '香港01', 'server' => 'hk.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    Node::create(['name' => '高级美国', 'server' => 'us.example.com', 'port' => 20086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 3, 'secret' => 's2']); // class3 用户看不到

    $yaml = app(SubscriptionService::class)->generateClash($user);
    $conf = Yaml::parse($yaml);

    $names = array_column($conf['proxies'], 'name');
    expect($names)->toContain('香港01');
    expect($names)->not->toContain('高级美国');   // 等级不够
});

it('injects user uuid into each vmess node', function () {
    $user = makeActiveUser();
    Node::create(['name' => '香港01', 'server' => 'hk.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);

    $conf = Yaml::parse(app(SubscriptionService::class)->generateClash($user));
    $node = $conf['proxies'][0];

    expect($node['uuid'])->toBe($user->uuid);
    expect($node['type'])->toBe('vmess');
    expect($node['alterId'])->toBe(0);
    expect($node['cipher'])->toBe('auto');
});

it('populates Proxy group with all node names', function () {
    $user = makeActiveUser();
    Node::create(['name' => '香港01', 'server' => 'hk.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    Node::create(['name' => '日本01', 'server' => 'jp.example.com', 'port' => 10087, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's2']);

    $conf = Yaml::parse(app(SubscriptionService::class)->generateClash($user));
    $proxyGroup = collect($conf['proxy-groups'])->firstWhere('name', 'Proxy');

    expect($proxyGroup['proxies'])->toContain('香港01', '日本01');
});

it('throws for expired user', function () {
    $user = User::factory()->create(['class' => 1, 'class_expire' => now()->subDay(), 'transfer_enable' => 100 * 1024 ** 3]);
    Node::create(['name' => '香港01', 'server' => 'hk.example.com', 'port' => 10086, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);

    expect(fn () => app(SubscriptionService::class)->generateClash($user))
        ->toThrow(RuntimeException::class);
});
