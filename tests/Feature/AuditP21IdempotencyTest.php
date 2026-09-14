<?php

use App\Models\Node;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use App\Services\TrafficService;

/**
 * 审计实验 P2-1 幂等 / 并发 / 重试 —— 只做观察，不修复。
 *
 * P0-2 已经审过订单结算与充值到账（都带行锁 + 状态复查，是对的）。
 * 这一轮看剩下两处：**流量上报**和**签到**。
 */
$GLOBALS['p21'] = 0;

function p21Node(): Node
{
    $i = ++$GLOBALS['p21'];

    return Node::create([
        'name' => 'P21-'.$i, 'server' => '10.21.0.'.$i, 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'node_class' => 0, 'traffic_rate' => 1.0,
        'secret' => 'P21'.$i, 'role' => 'landing', 'enabled' => true, 'online' => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────
// P21-A 流量上报没有去重 —— 与 agent 的 at-least-once 撞在一起
// ─────────────────────────────────────────────────────────────────
it('P21-A 同一批流量重放一次，用户就被扣两次', function () {
    $node = p21Node();
    $user = User::factory()->create(['class' => 0, 'u' => 0, 'd' => 0]);
    $batch = [['user_id' => $user->id, 'u' => 1_000, 'd' => 2_000]];

    app(TrafficService::class)->record($node, $batch);
    expect((int) $user->fresh()->u + (int) $user->fresh()->d)->toBe(3_000);

    // 完全相同的一批再来一次 —— 面板没有批次 id、没有请求 id、没有去重表
    app(TrafficService::class)->record($node, $batch);
    expect((int) $user->fresh()->u + (int) $user->fresh()->d)->toBe(6_000);

    // 节点侧的带宽账也跟着翻倍
    expect((int) \DB::table('node_daily_traffic')->where('node_id', $node->id)->value('billed'))
        ->toBe(6_000);
});

it('P21-A2 上报接口不接受任何幂等标识', function () {
    $node = p21Node();
    $user = User::factory()->create(['class' => 0]);

    // 带上常见的幂等字段名一起发，看面板是否会因此去重
    $send = fn () => $this->withHeaders([
        'X-Node-Id' => (string) $node->id, 'X-Node-Secret' => $node->secret,
    ])->postJson('/mod_mu/users/traffic', [
        'data' => [[
            'user_id' => $user->id, 'u' => 500, 'd' => 500,
            'batch_id' => 'B-1', 'request_id' => 'R-1', 'idempotency_key' => 'K-1',
        ]],
    ])->assertOk();

    $send();
    $send();

    // 两次都入账了 —— 这些字段被原样忽略
    expect((int) $user->fresh()->u + (int) $user->fresh()->d)->toBe(2_000);
});

// ─────────────────────────────────────────────────────────────────
// P21-B 签到：防重复签到是对的，但非会员分支会覆盖并发的额度变更
// ─────────────────────────────────────────────────────────────────
it('P21-B 同一天连签两次，第二次不发放（这一条是【好的】）', function () {
    $user = User::factory()->create(['class' => 0, 'transfer_enable' => 0, 'last_check_in' => 0]);
    $this->actingAs($user);

    $this->post('/user/checkin')->assertRedirect();
    $after = (int) $user->fresh()->transfer_enable;
    expect($after)->toBeGreaterThan(0);

    $this->post('/user/checkin')->assertRedirect();
    expect((int) $user->fresh()->transfer_enable)->toBe($after);
});

it('P21-B2 非会员签到写的是【绝对值】，会吞掉期间到账的加油包', function () {
    $gb = 1024 ** 3;
    $user = User::factory()->create([
        'class' => 0, 'transfer_enable' => 1 * $gb, 'last_check_in' => 0,
    ]);

    // 控制器拿到的是这一刻的 $user（读）
    $stale = User::find($user->id);
    expect((int) $stale->transfer_enable)->toBe(1 * $gb);

    // 读与写之间，另一条路径给他加了 10GB（买加油包 / 后台补偿）
    $pack = Plan::create([
        'name' => 'P21pack', 'price' => 10, 'period' => 'month', 'transfer_gb' => 10,
        'reset_type' => 'monthly', 'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0,
        'duration_days' => 30, 'sort' => 0, 'on_sale' => true, 'stock' => -1,
        'is_data_pack' => true,
    ]);
    app(BillingService::class)->applyDataPack($stale->fresh(), $pack);
    expect((int) $user->fresh()->transfer_enable)->toBe(11 * $gb);

    // 现在签到落库：min(stale + reward, cap) —— 基于【读那一刻】的 1GB
    $this->actingAs($stale);
    $this->post('/user/checkin')->assertRedirect();

    $final = (int) $user->fresh()->transfer_enable;
    // 10GB 的加油包被覆盖掉了：结果远小于 11GB
    expect($final)->toBeLessThan(11 * $gb);
    expect($final)->toBeLessThan(2 * $gb);   // 只剩 1GB + 几百 MB 奖励
});

it('P21-B3 对照：会员分支用 SQL 自增，不会吞掉并发变更', function () {
    $gb = 1024 ** 3;
    $plan = Plan::create([
        'name' => 'P21plan', 'price' => 100, 'period' => 'month', 'transfer_gb' => 1,
        'reset_type' => 'monthly', 'class' => 3, 'speed_limit' => 0, 'ip_limit' => 0,
        'duration_days' => 30, 'sort' => 0, 'on_sale' => true, 'stock' => -1,
    ]);
    $user = User::factory()->create(['last_check_in' => 0]);
    app(BillingService::class)->deliver($user, $plan);        // 成为会员，配额 1GB

    $stale = User::find($user->id);
    // 同样在读与写之间加 10GB
    \DB::table('users')->where('id', $user->id)->update(['transfer_enable' => 11 * $gb]);

    $this->actingAs($stale);
    $this->post('/user/checkin')->assertRedirect();

    // `[!!]` 会员走 DB::raw("transfer_enable + reward")，基于【库里的】值，
    // 所以 10GB 还在。同一个方法的两个分支，并发安全性不同。
    expect((int) $user->fresh()->transfer_enable)->toBeGreaterThan(11 * $gb);
});
