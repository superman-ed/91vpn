<?php

use App\Models\Node;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────
// `[!!]` hysteria(实为 hysteria2,UDP/QUIC)在 agent 侧【早已实现】并有
//   compatibility/hysteria.md 的完整对照,但面板的 'type' 校验只放
//   vmess/vless —— 能力有而建不出来。本组是放开那条通路的守卫。
//
// `[!!]` 三条约束不是面板的偏好,是 agent 会【拒绝启动整个节点】的条件:
//     transport 只能 tcp   (hysteria 不走 stream_type 这一套)
//     TLS 强制             (hysteria2 协议层要求,不存在明文 hysteria)
//     不能同时配 REALITY   (REALITY 只用于 vless)
//   不在面板拦,表现是"保存成功、节点起不来",而两边日志都要人去翻。
//
// `[!!]` 订阅有两个生成器,都必须有 hysteria 分支:
//   generateV2rayN 的末尾是【vmess 回落】—— 少了分支,hysteria 节点会被
//   当成 vmess 发出去:语法合法、协议完全不对、连不上且无从判断原因。
// ─────────────────────────────────────────────────────────────────

function hyAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'admin_role' => 'super', 'password' => 'a12345678']);
}

function hyPayload(array $over = []): array
{
    return array_merge([
        'name' => 'HK-Hy2', 'server' => 'hy.example.com', 'port' => 443,
        'type' => 'hysteria', 'net' => 'tcp', 'tls' => '1',
        'traffic_rate' => 1, 'node_class' => 0, 'role' => 'landing',
    ], $over);
}

it('可以建出 hysteria 节点', function () {
    $this->actingAs(hyAdmin())->post('/admin/nodes', hyPayload())->assertSessionHasNoErrors();

    $n = Node::where('name', 'HK-Hy2')->first();
    expect($n)->not->toBeNull();
    expect($n->type)->toBe('hysteria');
});

it('传输不是 tcp 时拒绝，并说明理由', function () {
    $this->actingAs(hyAdmin())
        ->post('/admin/nodes', hyPayload(['net' => 'ws']))
        ->assertSessionHasErrors('net');

    expect(Node::where('name', 'HK-Hy2')->count())->toBe(0);
});

it('没开 TLS 时拒绝 —— hysteria2 不存在明文', function () {
    $this->actingAs(hyAdmin())
        ->post('/admin/nodes', hyPayload(['tls' => '0']))
        ->assertSessionHasErrors('tls');

    expect(Node::where('name', 'HK-Hy2')->count())->toBe(0);
});

it('同时配 REALITY 时拒绝', function () {
    $this->actingAs(hyAdmin())
        ->post('/admin/nodes', hyPayload(['reality_enabled' => '1', 'reality_dest' => 'x.com:443']))
        ->assertSessionHasErrors('reality_dest');
});

// ── 订阅两个格式 ──────────────────────────────────────────────

function hySubUser(): User
{
    return User::factory()->create([
        'invite_token' => 'HYTOKEN', 'class' => 2, 'class_expire' => now()->addMonth(),
        'transfer_enable' => 100 * 1024 ** 3, 'u' => 0, 'd' => 0, 'password' => 'x12345678',
    ]);
}

function hyNode(): Node
{
    return Node::create([
        'name' => 'HK-Hy2', 'server' => 'hy.example.com', 'port' => 443, 'type' => 'hysteria',
        'net' => 'tcp', 'tls' => true, 'host' => 'sni.example.com', 'traffic_rate' => 1,
        'node_class' => 0, 'secret' => 'HYS', 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time(),
    ]);
}

it('Clash 订阅出 hysteria2，凭据是用户密码且带 alpn=h3', function () {
    $u = hySubUser();
    hyNode();

    $yaml = $this->get('/sub/HYTOKEN')->assertOk()->getContent();
    $cfg = \Symfony\Component\Yaml\Yaml::parse($yaml);
    $p = collect($cfg['proxies'])->firstWhere('name', 'HK-Hy2');

    expect($p)->not->toBeNull();
    expect($p['type'])->toBe('hysteria2');
    expect($p['password'])->toBe($u->passwd, '用了 uuid 而不是密码 —— 会静默认证失败');
    expect($p['alpn'])->toBe(['h3'], 'alpn 不是 h3 —— 表现是端口在听但连不上,两边都不报错');
    expect($p['sni'])->toBe('sni.example.com');
});

// `[!!]` 这一条守的是 generateV2rayN 末尾那个 vmess 回落。
it('base64 订阅出 hysteria2:// 而不是回落成 vmess://', function () {
    $u = hySubUser();
    hyNode();

    $raw = base64_decode($this->get('/sub/HYTOKEN?flag=v2ray')->assertOk()->getContent());

    expect($raw)->toContain('hysteria2://');
    expect(str_contains($raw, 'vmess://'))->toBeFalse('hysteria 节点被当成 vmess 发出去了');
    expect($raw)->toContain('alpn=h3');
    expect($raw)->toContain(rawurlencode($u->passwd));
});

it('私钥与 uuid 不会出现在 hysteria 订阅里', function () {
    $u = hySubUser();
    hyNode();

    $yaml = $this->get('/sub/HYTOKEN')->assertOk()->getContent();

    expect(str_contains($yaml, $u->uuid))->toBeFalse('hysteria 条目里出现了 uuid');
});
