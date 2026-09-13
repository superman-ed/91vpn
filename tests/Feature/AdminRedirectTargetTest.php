<?php

use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\User;

/**
 * 管理端跳转的【目的地要存在】。
 *
 * `[!!]` 2026-09-13 发现：保存与删除规则之后一律跳到 `/rules`，
 * 而实际路由带 `admin/` 前缀 —— **全部落在 404**。
 * 那条"规则已保存，但【节点会拒绝它】—— 见下方检查结果"的提示
 * 因此从来没人看得见。
 *
 * `[!!]` 634 条测试没抓到它，因为它们都只写 `assertRedirect()` ——
 * 那只证明"发生了跳转"，不证明目的地存在。这一组专门跟进去看。
 */
function artNode(string $ip, int $seq): Node
{
    return Node::create([
        'name' => 'R'.$seq, 'server' => $ip, 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'AR'.$seq,
        'role' => 'relay', 'enabled' => true, 'online' => true, 'last_heartbeat' => time(),
    ]);
}

function artRule(Node $relay, int $seq): ForwardRule
{
    return ForwardRule::create([
        'name' => 'r'.$seq, 'enabled' => true, 'listen_port' => (string) (46000 + $seq),
        'inbound_node_set' => [$relay->id], 'inbound_type' => 'direct',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
    ]);
}

/** 跟着 Location 走一趟，回来的必须不是 404。 */
function artFollow($test, User $admin, $response): int
{
    $loc = $response->headers->get('Location');
    expect($loc)->toBeString();
    // `[!]` path 为 null 说明跳的是站点根 —— 那通常是 back() 在表单校验失败时
    // 的回退，不是我们要验的那条跳转。当成失败报出来，别悄悄当成 200。
    $path = parse_url($loc, PHP_URL_PATH);
    expect($path)->not->toBeNull("跳到了站点根（{$loc}）—— 多半是请求本身就失败了");

    return $test->actingAs($admin)->get($path)->status();
}

it('删除规则之后跳到的页面是存在的', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $rule = artRule(artNode('198.51.100.21', 21), 21);

    $r = $this->actingAs($admin)->delete("/admin/rules/{$rule->id}");
    expect(artFollow($this, $admin, $r))->toBe(200);
});

it('保存规则之后跳到的页面是存在的', function () {
    // `[!]` 载荷在本文件里自带一份，【不】借用 RelayAdminPagesTest 的
    // ruleFormPayload() —— Pest 的跨文件全局函数只在整套一起跑时才存在，
    // 单跑这个文件会 undefined function。测试之间互相依赖全局函数，
    // 失败信息还会指向一个和真因无关的地方。
    $admin = User::factory()->create(['is_admin' => true]);
    $relay = artNode('198.51.100.22', 22);

    $r = $this->actingAs($admin)->post('/admin/rules', [
        'name' => '新规则', 'enabled' => 1, 'speed_limit' => 0,
        'inbound_type' => 'direct', 'inbound_node_set' => [$relay->id],
        'listen_port' => '46999', 'balance' => 'roundrobin',
        'backup_balance' => 'fallback', 'hc_enabled' => 1,
        'hc_interval_sec' => 30, 'hc_max_fail' => 3, 'hc_max_success' => 2,
        'out_type' => ['direct'], 'out_pool' => ['primary'], 'out_enabled' => [1],
        'out_target_addr' => ['9.9.9.9'], 'out_target_port' => ['443'],
    ]);
    expect(artFollow($this, $admin, $r))->toBe(200);
});

it('一键收编之后跳到的页面是存在的', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $rule = artRule(artNode('198.51.100.23', 23), 23);

    $r = $this->actingAs($admin)->post("/admin/rules/{$rule->id}/adopt-targets");
    expect(artFollow($this, $admin, $r))->toBe(200);
});
