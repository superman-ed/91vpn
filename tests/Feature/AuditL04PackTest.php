<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

/**
 * L-04 消费端验证：用户付钱买的流量包，在【他自己看到的页面上】怎么变化。
 *
 * `[!!]` 之前的 P11-A 直接调 applyDataPack()。那证明了服务层的算法，
 * 没证明用户真的经历得到 —— 购买要走下单、付款、发货三步，
 * 任何一步拦下来，这条结论就不成立。这里全程走真实 HTTP。
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

it('L04-2 结账页承诺的是「立即叠加到当前套餐」，没有任何有效期字样', function () {
    $user = User::factory()->create(['money' => 100]);
    l04Buy($this, $user, l04Plan());

    $pack = l04Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0]);
    $this->actingAs($user->fresh());
    $this->post('/user/order/create', ['plan_id' => $pack->id])->assertRedirect();
    $order = Order::where('user_id', $user->id)->latest('id')->firstOrFail();

    $html = $this->get("/user/order/{$order->id}")->assertOk()->getContent();
    expect($html)->toContain('立即叠加 50GB 到当前套餐');

    // `[!]` 只看【订单详情卡片】，不看整页。
    // 整页会命中布局里的"账号到期时间"—— 那说的是账号，不是这个流量包。
    // 拿整页断言会把一条无关的字当成"页面已经说明了有效期"，结论就反了。
    preg_match('/<div class="card-body co-summary">(.*?)<\/div>\s*<\/div>/s', $html, $m);
    $card = $m[1] ?? '';
    expect($card)->not->toBe('');
    expect($card)->toContain('立即叠加 50GB 到当前套餐');
    foreach (['有效期', '到期', '重置', '次月', '失效', '收回'] as $word) {
        expect($card)->not->toContain($word);
    }
});

it('L04-3 月度重置跑过之后，用户仪表盘上那 50GB 不见了', function () {
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

it('L04-4 而且用户在系统里找不到任何解释 —— 订单还在，流量没了', function () {
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

    // `[!]` 两头都成立才是这条结论的完整形态：
    // 钱付了、订单在、流量没了、系统里没有一处说得出为什么。
    expect(l04RemainGb($this, $user))->toBe('100.0 GB');
});

it('L04-5 对照：不跑重置时 50GB 一直在 —— 消失确实是重置造成的', function () {
    $user = User::factory()->create(['money' => 100]);
    l04Buy($this, $user, l04Plan());
    l04Buy($this, $user, l04Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0]));

    // 重置日还在未来 → 定时任务扫过但不动这个用户
    $user->fresh()->update(['next_reset_at' => now()->addMonth()]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    expect(l04RemainGb($this, $user))->toBe('150.0 GB');
});
