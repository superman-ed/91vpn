<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

/**
 * 流量包的清零规则 —— 规格测试。
 *
 * `[!!]` 这组用例最初是【审计实验】，断言的是"用户付费买的流量包会凭空消失"。
 * 后来 owner 确认那是**有意设计**：流量包在会员到期日或流量重置日清零。
 * 于是断言反转，文件留下来当规格守卫。
 *
 * `[!]` 不要按"修复缺陷"的思路改这里的行为。两条别走的路：
 *   · 恢复 users.pack_transfer 列（2026_08_24_100008 建过，100009 又删了，后者是现行意图）
 *   · 让 applyDataPack() 同时抬高 base_transfer_enable
 *     —— 那会让会员【每月白拿一份】，从少给变成多给
 * 见 docs/LAUNCH-CHECKLIST.md L-04。
 *
 * 全程走真实 HTTP：下单、付款、发货三步都真的发生，
 * 判据落在用户自己看到的页面上 —— 服务层算对了不等于用户经历得到。
 */
$GLOBALS['l04'] = 0;

function l04Plan(array $over = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'L04-'.(++$GLOBALS['l04']), 'price' => 10, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 3,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1, 'is_data_pack' => false,
    ], $over));
}

/** 走真实下单 + 余额付款。返回订单。 */
function l04Buy(object $t, User $u, Plan $plan): Order
{
    $t->actingAs($u->fresh());
    $t->post('/user/order/create', ['plan_id' => $plan->id])->assertRedirect();
    $order = Order::where('user_id', $u->id)->latest('id')->firstOrFail();
    $t->post("/user/order/{$order->id}/pay-balance")->assertRedirect();

    return $order->fresh();
}

/** 用户仪表盘上「剩余流量」那个数字。 */
function l04RemainGb(object $t, User $u): string
{
    $t->actingAs($u->fresh());
    $html = $t->get('/user')->assertOk()->getContent();
    preg_match('/剩余流量.*?stat-value text-success">([^<]+)</s', $html, $m);

    return trim($m[1] ?? '(没抓到)');
}

it('L04-1 买流量包 → 仪表盘数字确实涨了（装置自检）', function () {
    $user = User::factory()->create(['money' => 100]);
    $base = l04Plan();
    $pack = l04Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0]);

    $o1 = l04Buy($this, $user, $base);
    expect($o1->status)->toBe('paid');
    expect(l04RemainGb($this, $user))->toBe('100.0 GB');

    $o2 = l04Buy($this, $user, $pack);
    expect($o2->status)->toBe('paid');
    expect(l04RemainGb($this, $user))->toBe('150.0 GB');
});

it('L04-2 结账页在【购买前】就把清零规则说清楚', function () {
    $user = User::factory()->create(['money' => 100]);
    l04Buy($this, $user, l04Plan());

    $pack = l04Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0]);
    $this->actingAs($user->fresh());
    $this->post('/user/order/create', ['plan_id' => $pack->id])->assertRedirect();
    $order = Order::where('user_id', $user->id)->latest('id')->firstOrFail();

    $html = $this->get("/user/order/{$order->id}")->assertOk()->getContent();

    // `[!]` 只看【订单详情卡片】，不看整页。
    // 整页会命中布局里的"账号到期时间"—— 那说的是账号，不是这个流量包。
    // 拿整页断言会把一条无关的字当成"页面已经说明了"，结论就反了。
    preg_match('/<div class="card-body co-summary">(.*?)<\/div>\s*<\/div>/s', $html, $m);
    $card = $m[1] ?? '';
    expect($card)->not->toBe('');

    expect($card)->toContain('立即叠加 50GB 到当前套餐');
    // 逐字钉住 —— 这是 owner 定的对外口径，改动要有人明确决定
    expect($card)->toContain(
        '购买的流量包将会在您的会员到期日或流量重置日自动清零，请根据您的实际使用流量选择合适的流量包。'
    );
});

it('L04-2b 普通套餐不显示这句话 —— 它只对流量包成立', function () {
    $user = User::factory()->create(['money' => 100]);

    $this->actingAs($user);
    $this->post('/user/order/create', ['plan_id' => l04Plan()->id])->assertRedirect();
    $order = Order::where('user_id', $user->id)->latest('id')->firstOrFail();

    $html = $this->get("/user/order/{$order->id}")->assertOk()->getContent();
    preg_match('/<div class="card-body co-summary">(.*?)<\/div>\s*<\/div>/s', $html, $m);
    $card = $m[1] ?? '';
    expect($card)->not->toBe('');
    expect($card)->not->toContain('自动清零');
    // 反向对照：确认抓到的确实是订单卡片，不是空串蒙混过关
    expect($card)->toContain('每月 100GB');
});

it('L04-3 规格：月度重置把流量包清零（用户仪表盘上可见）', function () {
    $user = User::factory()->create(['money' => 100]);
    l04Buy($this, $user, l04Plan());
    l04Buy($this, $user, l04Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0]));
    expect(l04RemainGb($this, $user))->toBe('150.0 GB');

    // 把重置日拨到过去 —— 这是定时任务每天都会做的判断
    $user->fresh()->update(['next_reset_at' => now()->subMinute()]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    // `[!!]` 消费端：用户自己打开面板看到的数字
    expect(l04RemainGb($this, $user))->toBe('100.0 GB');
});

it('L04-4 购买时已告知，但【事后】系统里仍然没有任何记录（L-09 未解决）', function () {
    $user = User::factory()->create(['money' => 100]);
    l04Buy($this, $user, l04Plan());
    $packOrder = l04Buy($this, $user, l04Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0]));

    $user->fresh()->update(['next_reset_at' => now()->subMinute()]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    // 订单记录还在，明明白白写着他买过
    $this->actingAs($user->fresh());
    $wallet = $this->get('/user/wallet')->assertOk()->getContent();
    expect($wallet)->toContain($packOrder->order_no);
    expect($packOrder->fresh()->status)->toBe('paid');

    // 流量明细页也不会显示"被系统收回 50GB"
    $traffic = $this->get('/user/traffic')->assertOk()->getContent();
    foreach (['回收', '重置', '清零', '扣减'] as $word) {
        expect($traffic)->not->toContain($word);
    }

    // `[!]` 清零本身已经在购买前告知了（L04-2），所以这不再是"没打招呼"。
    // 仍然成立的是【事后不可追溯】：具体哪一次重置、抹掉了多少，
    // 系统里没有一处记得住。那是 L-09，尚未解决。
    expect(l04RemainGb($this, $user))->toBe('100.0 GB');
});

it('L04-5 对照：重置日未到时流量包不动 —— 清零确实由重置触发', function () {
    $user = User::factory()->create(['money' => 100]);
    l04Buy($this, $user, l04Plan());
    l04Buy($this, $user, l04Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0]));

    // 重置日还在未来 → 定时任务扫过但不动这个用户
    $user->fresh()->update(['next_reset_at' => now()->addMonth()]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    expect(l04RemainGb($this, $user))->toBe('150.0 GB');
});
