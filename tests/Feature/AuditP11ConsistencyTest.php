<?php

use App\Models\Node;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use App\Services\TrafficService;
use Illuminate\Support\Facades\DB;

/**
 * 审计实验 P1-1 数据一致性 —— 只做观察，不修复。
 *
 * 审的是【同一个事实有两个存放处时，它们会不会分叉，分叉了会不会被发现】。
 *
 * `[!!]` 这里的每个断言都写成「钉住当前行为」，不是「断言期望行为」——
 * 因为本轮还没到修的阶段。若将来行为改了，测试会红，那正是要的信号：
 * 提醒改的人回来看这份审计结论是不是也该更新。
 */
$GLOBALS['p11'] = 0;

function p11Plan(array $over = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'P11-'.(++$GLOBALS['p11']), 'price' => 100, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 3,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1, 'is_data_pack' => false,
    ], $over));
}

function p11User(array $over = []): User
{
    return User::factory()->create($over);
}

// ─────────────────────────────────────────────────────────────────
// P11-A 已付费的流量包会在月度重置时被清零
// ─────────────────────────────────────────────────────────────────
it('P11-A 加油包在月度重置时被清零：用户付了钱的流量凭空消失', function () {
    $gb = 1024 ** 3;
    $user = p11User();
    // 先正常开通一个 100GB 月付套餐
    app(BillingService::class)->deliver($user, p11Plan());
    $user->refresh();
    expect((int) $user->transfer_enable)->toBe(100 * $gb)
        ->and((int) $user->base_transfer_enable)->toBe(100 * $gb);

    // 再买一个 50GB 加油包
    app(BillingService::class)->applyDataPack($user, p11Plan(['transfer_gb' => 50, 'is_data_pack' => true]));
    $user->refresh();
    $afterPack = (int) $user->transfer_enable;
    expect($afterPack)->toBe(150 * $gb);
    // `[!]` 关键：加油包【没有】抬高 base_transfer_enable —— 重置基准仍是 100GB
    expect((int) $user->base_transfer_enable)->toBe(100 * $gb);

    // 把重置日拨到过去，跑一次月度重置
    $user->update(['next_reset_at' => now()->subMinute()]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();
    $user->refresh();

    expect((int) $user->transfer_enable)->toBe(100 * $gb);   // 50GB 没了
    $lost = $afterPack - (int) $user->transfer_enable;
    expect($lost)->toBe(50 * $gb);
});

it('P11-A2 加油包能存活多久取决于用户自己的 next_reset_at —— 最坏情况几乎全损', function () {
    $gb = 1024 ** 3;
    $user = p11User();
    app(BillingService::class)->deliver($user, p11Plan());
    // 重置日就在 1 分钟后（用户看不到这个日期）
    $user->update(['next_reset_at' => now()->addMinute()]);

    app(BillingService::class)->applyDataPack($user, p11Plan(['transfer_gb' => 500, 'is_data_pack' => true]));
    $user->refresh();
    expect((int) $user->transfer_enable)->toBe(600 * $gb);

    $this->travel(2)->minutes();
    $this->artisan('traffic:reset-monthly')->assertSuccessful();
    $user->refresh();

    // 买了 500GB，两分钟后一点不剩
    expect((int) $user->transfer_enable)->toBe(100 * $gb);
});

it('P11-A3 新买一个套餐同样会覆盖掉未用完的加油包', function () {
    $gb = 1024 ** 3;
    $user = p11User();
    $billing = app(BillingService::class);
    $billing->deliver($user, p11Plan());
    $billing->applyDataPack($user, p11Plan(['transfer_gb' => 200, 'is_data_pack' => true]));
    $user->refresh();
    expect((int) $user->transfer_enable)->toBe(300 * $gb);

    // deliver() 是覆盖写，不是增量写
    $billing->deliver($user, p11Plan());
    $user->refresh();
    expect((int) $user->transfer_enable)->toBe(100 * $gb);
});

// ─────────────────────────────────────────────────────────────────
// P11-B 被归属校验拒收的流量，从【所有】账本里同时消失
// ─────────────────────────────────────────────────────────────────
function p11Node(int $minClass = 0): Node
{
    return Node::create([
        'name' => 'P11节点'.(++$GLOBALS['p11']), 'server' => '127.0.0.1', 'port' => 443,
        'type' => 'vless', 'node_class' => $minClass, 'traffic_rate' => 1.0, 'secret' => 'p11-'.bin2hex(random_bytes(8)),
        'status' => 1, 'enabled' => true, 'sort' => 0,
    ]);
}

it('P11-B 被拒收的用户流量既不进用户账，也不进节点带宽统计', function () {
    $node = p11Node(3);                       // 节点要求 class >= 3
    $ok = p11User(['class' => 3]);
    $banned = p11User(['class' => 3, 'banned' => true]);
    $lowClass = p11User(['class' => 0]);             // 等级不够

    $n = app(TrafficService::class)->record($node, [
        ['user_id' => $ok->id,       'u' => 1_000, 'd' => 2_000],
        ['user_id' => $banned->id,   'u' => 5_000, 'd' => 5_000],
        ['user_id' => $lowClass->id, 'u' => 7_000, 'd' => 7_000],
    ]);

    expect($n)->toBe(1);                             // 只有 1 个用户入账
    expect((int) $ok->fresh()->u + (int) $ok->fresh()->d)->toBe(3_000);
    expect((int) $banned->fresh()->u)->toBe(0);
    expect((int) $lowClass->fresh()->u)->toBe(0);

    // `[!!]` 这是本条的要害：节点带宽统计【也】只记了 3,000。
    // 字段注释写的是「原始上行(服务器真实带宽)」，而服务器真实转发了 27,000 字节。
    $ndt = DB::table('node_daily_traffic')->where('node_id', $node->id)->first();
    expect((int) $ndt->u + (int) $ndt->d)->toBe(3_000);
    expect((int) $ndt->u + (int) $ndt->d)->not->toBe(27_000);
});

it('P11-B2 拒收是静默的：非法记录会写 warning，归属不符的不会', function () {
    $node = p11Node(3);
    $lowClass = p11User(['class' => 0]);

    $logged = [];
    Illuminate\Support\Facades\Log::listen(function ($e) use (&$logged) {
        $logged[] = $e->level.': '.$e->message;
    });

    app(TrafficService::class)->record($node, [
        ['user_id' => $lowClass->id, 'u' => 9_999, 'd' => 0],
    ]);

    // 对照：负数记录【会】留下 warning —— 证明这里有日志能力，是这一类没用
    $warnForAttribution = array_filter($logged, fn ($l) => str_contains($l, '丢弃'));
    expect($warnForAttribution)->toBe([]);

    $logged = [];
    app(TrafficService::class)->record($node, [
        ['user_id' => $lowClass->id, 'u' => -1, 'd' => 0],
    ]);
    expect(array_values(array_filter($logged, fn ($l) => str_contains($l, '丢弃'))))->not->toBe([]);
});

// ─────────────────────────────────────────────────────────────────
// P11-C traffic_logs 是死表
// ─────────────────────────────────────────────────────────────────
it('P11-C traffic_logs 有表有索引，但没有任何写入方', function () {
    $node = p11Node();
    $user = p11User(['class' => 0]);

    app(TrafficService::class)->record($node, [['user_id' => $user->id, 'u' => 1_000, 'd' => 1_000]]);

    expect(DB::table('traffic_logs')->count())->toBe(0);
    // 用户侧/节点侧都记了，唯独「这笔流量走的哪个节点」没有任何地方记录
    expect((int) $user->fresh()->u)->toBe(1_000);
    expect(DB::table('node_daily_traffic')->where('node_id', $node->id)->count())->toBe(1);
    expect(DB::table('daily_traffic')->where('user_id', $user->id)->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────────
// P11-D 余额 ↔ 流水（对照组：这一条是【好的】，写出来是为了把范围钉死）
// ─────────────────────────────────────────────────────────────────
it('P11-D 每条余额变动都有流水，且 balance_after 与最终余额一致', function () {
    $user = p11User(['money' => 0]);
    $billing = app(BillingService::class);

    $billing->applyRecharge($user, 100.00, 'T-1', '测试充值');
    $billing->adminAdjust($user->fresh(), 80.00, 'auditor');

    $user->refresh();
    $logs = DB::table('balance_logs')->where('user_id', $user->id)->orderBy('id')->get();

    expect($logs)->toHaveCount(2);
    expect((float) $logs->last()->balance_after)->toBe((float) $user->money);
    expect(round($logs->sum(fn ($l) => (float) $l->amount), 2))->toBe(round((float) $user->money, 2));
});
