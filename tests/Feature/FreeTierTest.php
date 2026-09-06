<?php

use App\Models\Setting;
use App\Models\User;

// 免费签到流量档:非会员签到封顶 + 免费节点(node_class=0)可连 + 付费节点仍拒绝 +
// 过期会员降级到免费档并受免费封顶约束。makeNode() 见 NodeApiTest.php(默认 node_class=0)。

beforeEach(function () {
    Setting::put('free_traffic_cap_gb', '2');   // 免费封顶 2GB
});

it('caps free (non-member) check-in traffic at the free cap and sets a regen date', function () {
    // 非会员,已有 1.9GB;再签到最多到 2GB 封顶
    $user = User::factory()->create([
        'class' => 0, 'class_expire' => now(), 'last_check_in' => 0,
        'transfer_enable' => (int) (1.9 * 1024 ** 3), 'next_reset_at' => null,
    ]);

    $this->actingAs($user)->post('/user/checkin')->assertRedirect();

    $fresh = $user->fresh();
    expect((int) $fresh->transfer_enable)->toBe(2 * 1024 ** 3);   // 封顶
    expect($fresh->next_reset_at)->not->toBeNull();               // 设定每月再生日
});

it('does not cap members on check-in', function () {
    $user = User::factory()->create([
        'class' => 1, 'class_expire' => now()->addDays(10), 'last_check_in' => 0,
        'transfer_enable' => 100 * 1024 ** 3,
    ]);

    $this->actingAs($user)->post('/user/checkin')->assertRedirect();

    expect((int) $user->fresh()->transfer_enable)->toBeGreaterThan(100 * 1024 ** 3);
});

it('serves non-members with free traffic on a free node, at the node speed', function () {
    $free = makeNode(['node_class' => 0, 'speed_limit' => 5]);
    // 非会员,有免费流量剩余(在封顶内)
    $ok = User::factory()->create(['class' => 0, 'class_expire' => now(), 'transfer_enable' => 1024 ** 3, 'u' => 0, 'd' => 0]);
    // 非会员,已用已超免费封顶 → 不服务
    User::factory()->create(['class' => 0, 'class_expire' => now(), 'transfer_enable' => 5 * 1024 ** 3, 'u' => 3 * 1024 ** 3, 'd' => 0]);

    $data = $this->getJson("/mod_mu/users?node_id={$free->id}&key=NODESECRET")->assertOk()->json('data');

    expect(collect($data)->pluck('uuid'))->toContain($ok->uuid);
    expect($data)->toHaveCount(1);
    // 免费用户下发节点自带限速
    expect((int) collect($data)->firstWhere('uuid', $ok->uuid)['speed_limit'])->toBe(5);
});

it('does not serve non-members on a paid node', function () {
    $paid = makeNode(['node_class' => 1]);
    User::factory()->create(['class' => 0, 'class_expire' => now(), 'transfer_enable' => 1024 ** 3, 'u' => 0, 'd' => 0]);

    $data = $this->getJson("/mod_mu/users?node_id={$paid->id}&key=NODESECRET")->assertOk()->json('data');

    expect($data)->toHaveCount(0);
});

it('caps expired-member usage on a free node by the free cap', function () {
    $free = makeNode(['node_class' => 0]);
    // 过期会员,旧套餐大额度,但已用超过免费封顶 → 免费节点不再服务
    User::factory()->create(['class' => 3, 'class_expire' => now()->subDay(), 'transfer_enable' => 100 * 1024 ** 3, 'u' => 3 * 1024 ** 3, 'd' => 0]);
    // 过期会员,已用在免费封顶内 → 可连免费节点烧剩余
    $ok = User::factory()->create(['class' => 3, 'class_expire' => now()->subDay(), 'transfer_enable' => 100 * 1024 ** 3, 'u' => 1024 ** 3, 'd' => 0]);

    $data = $this->getJson("/mod_mu/users?node_id={$free->id}&key=NODESECRET")->assertOk()->json('data');

    expect(collect($data)->pluck('uuid'))->toContain($ok->uuid);
    expect($data)->toHaveCount(1);
});

it('lets an active member use full quota on a free node (not limited by free cap)', function () {
    $free = makeNode(['node_class' => 0]);
    // 会员,已用超过免费封顶但在自身额度内 → 免费节点照常服务(会员不受免费封顶约束)
    $member = User::factory()->create(['class' => 2, 'class_expire' => now()->addDays(10), 'transfer_enable' => 100 * 1024 ** 3, 'u' => 3 * 1024 ** 3, 'd' => 0]);

    $data = $this->getJson("/mod_mu/users?node_id={$free->id}&key=NODESECRET")->assertOk()->json('data');

    expect(collect($data)->pluck('uuid'))->toContain($member->uuid);
});
