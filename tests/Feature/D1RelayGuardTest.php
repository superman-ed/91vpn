<?php

use App\Models\Node;
use App\Models\User;

/**
 * D-1 守卫：中转 / 跳板 / 入口一律不认证、不持有用户名单，只透传字节。
 *
 * `[!!]` 这组用例是**合并 relaypanel 的前置条件**（ADR-008）。
 * 拆分成两个面板时，中转拿不到用户数据是因为它**根本连不到那个库**；
 * 合并之后，这件事只由 `Node::needsUsers()` 一个判断保证 ——
 * **那个判断错一次，中转机就拿到全部用户凭据**，而中转机往往是租来的、
 * 最容易被接管的那一台。
 *
 * 所以这里守两个方向：
 *   读 —— 任何端点都不能把用户 uuid/passwd 交给 relay 角色；
 *   写 —— relay 不能替任意用户上报流量或在线 IP（否则计费与审计可被污染）。
 */
function relayNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'relay', 'server' => 'r', 'port' => 0, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'RELAYSECRET', 'role' => 'relay',
    ], $attr));
}

function landingNode(array $attr = []): Node
{
    return Node::create(array_merge([
        'name' => 'landing', 'server' => 'l', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'LANDSECRET', 'role' => 'landing',
    ], $attr));
}

function servableUser(): User
{
    return User::factory()->create([
        'class' => 5, 'class_expire' => now()->addDay(),
        'transfer_enable' => 1024 ** 3, 'u' => 0, 'd' => 0,
    ]);
}

/**
 * `[!!]` 契约测试：遍历**全部** GET 端点，断言 relay 角色在任何一个上
 * 都拿不到用户凭据。新增端点若忘了守卫，这条会红 ——
 * 这正是它存在的理由（单点守卫会被下一个端点绕过）。
 */
it('relay 角色在任何读端点上都拿不到用户凭据', function () {
    $relay = relayNode();
    $u = servableUser();
    $q = "node_id={$relay->id}&key=RELAYSECRET";

    $endpoints = [
        "/mod_mu/users?{$q}",
        "/mod_mu/func/detect_rules?{$q}",
        "/mod_mu/func/ping?{$q}",
        "/mod_mu/nodes/{$relay->id}/info?key=RELAYSECRET",
    ];

    foreach ($endpoints as $ep) {
        $body = $this->getJson($ep)->assertOk()->getContent();
        expect($body)->not->toContain($u->uuid, "端点 {$ep} 把用户 uuid 交给了中转");
        if ($u->passwd) {
            expect($body)->not->toContain($u->passwd, "端点 {$ep} 把用户密码交给了中转");
        }
    }
});

// 对照：落地角色【应该】拿得到，否则上面那条可能只是"谁都拿不到"。
it('落地角色照常拿得到用户名单', function () {
    $landing = landingNode();
    $u = servableUser();

    $this->getJson("/mod_mu/users?node_id={$landing->id}&key=LANDSECRET")
        ->assertOk()->assertJsonFragment(['uuid' => $u->uuid]);
});

// `[!!]` 写方向：一台被接管的中转不能替任意用户记流量 —— 那是计费。
it('relay 角色上报的按用户流量不予采纳', function () {
    $relay = relayNode();
    $u = servableUser();

    $this->postJson("/mod_mu/users/traffic?node_id={$relay->id}&key=RELAYSECRET", [
        'data' => [['user_id' => $u->id, 'u' => 999_000_000, 'd' => 999_000_000]],
    ])->assertOk();

    $u->refresh();
    expect((int) $u->u)->toBe(0, '中转上报的流量被记到了用户头上');
    expect((int) $u->d)->toBe(0);
});

// 同理：在线 IP 是审计与设备数限制的依据，中转不该能写。
it('relay 角色上报的在线 IP 不予采纳', function () {
    $relay = relayNode();
    $u = servableUser();

    $this->postJson("/mod_mu/users/aliveip?node_id={$relay->id}&key=RELAYSECRET", [
        'data' => [['user_id' => $u->id, 'ip' => '203.0.113.9']],
    ])->assertOk();

    expect(\Illuminate\Support\Facades\DB::table('alive_ips')->where('user_id', $u->id)->count())
        ->toBe(0, '中转上报的在线 IP 被采纳了');
});

// 对照：落地上报照常生效。
it('落地角色上报流量与在线 IP 照常生效', function () {
    $landing = landingNode();
    $u = servableUser();

    $this->postJson("/mod_mu/users/traffic?node_id={$landing->id}&key=LANDSECRET", [
        'data' => [['user_id' => $u->id, 'u' => 1000, 'd' => 2000]],
    ])->assertOk();
    $this->postJson("/mod_mu/users/aliveip?node_id={$landing->id}&key=LANDSECRET", [
        'data' => [['user_id' => $u->id, 'ip' => '203.0.113.9']],
    ])->assertOk();

    expect((int) $u->refresh()->d)->toBeGreaterThan(0);
    expect(\Illuminate\Support\Facades\DB::table('alive_ips')->where('user_id', $u->id)->count())
        ->toBeGreaterThan(0);
});
