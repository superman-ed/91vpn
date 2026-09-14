<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

/**
 * L-09 消费端验证：出事之后去后台「操作日志」页，能查到什么。
 *
 * `[!!]` 之前的 P14 断言的是 AuditLog::count() —— 那是表。
 * 而人真正会做的动作是：打开 /admin/system/audit，按用户搜一下。
 * 所以判据要落在【那一页渲染出来的内容】上。
 */
$GLOBALS['l09'] = 0;

function l09Plan(array $over = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'L09-'.(++$GLOBALS['l09']), 'price' => 10, 'period' => 'month',
        'transfer_gb' => 100, 'reset_type' => 'monthly', 'class' => 3,
        'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1, 'is_data_pack' => false,
    ], $over));
}

/** 以管理员身份打开操作日志页，按关键词搜。 */
function l09Page(object $t, string $q = ''): string
{
    $t->actingAs(User::factory()->create(['is_admin' => true]));

    return $t->get('/admin/system/audit'.($q !== '' ? '?q='.urlencode($q) : ''))
        ->assertOk()->getContent();
}

it('L09-1 对照：人在后台改了用户，日志页上查得到（装置自检）', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['class' => 0, 'money' => 0]);

    $this->actingAs($admin);
    $this->put("/admin/users/{$target->id}", [
        'class' => 3, 'transfer_enable_gb' => 100, 'money' => 0, 'original_money' => 0,
    ])->assertRedirect();

    $this->actingAs($admin);
    $html = $this->get('/admin/system/audit')->assertOk()->getContent();
    expect($html)->toContain($target->ident());
    expect($html)->toContain($admin->email);      // 署名是具体的人
    expect($html)->not->toContain('暂无操作记录');
});

it('L09-2 月度重置抹掉用户已付费的流量包，日志页上一个字都没有', function () {
    $user = User::factory()->create(['money' => 100]);
    $this->actingAs($user);
    // 走真实购买，确保这是一次真的付费行为
    foreach ([l09Plan(), l09Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0])] as $p) {
        $this->actingAs($user->fresh());
        $this->post('/user/order/create', ['plan_id' => $p->id])->assertRedirect();
        $o = Order::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->post("/user/order/{$o->id}/pay-balance")->assertRedirect();
    }
    expect((int) $user->fresh()->transfer_enable)->toBe(150 * 1024 ** 3);

    AuditLog::query()->delete();                       // 只看这一步产生了什么
    $user->fresh()->update(['next_reset_at' => now()->subMinute()]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();

    // 前置条件：这一步确实改了用户的权益
    expect((int) $user->fresh()->transfer_enable)->toBe(100 * 1024 ** 3);

    // `[!!]` 消费端：客服/运营打开日志页，按这个用户搜
    $html = l09Page($this, $user->email);
    expect($html)->toContain('暂无操作记录');

    // `[!]` 这里【不能】断言"页面里没有这个邮箱"—— 搜索框会把 q 原样回显，
    // 所以搜什么词，页面上就一定有什么词。那条断言永远失败，
    // 而它失败的原因与本条结论毫无关系。判据只能是空状态本身。
    expect(AuditLog::count())->toBe(0);
});

it('L09-3 自动发货把用户等级从 0 改到 3，日志页同样查不到', function () {
    $user = User::factory()->create(['class' => 0]);
    $plan = l09Plan();
    Order::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 10,
        'status' => 'queued', 'period' => 'month', 'order_no' => 'L09Q',
        'activate_at' => now()->subMinute(),
    ]);

    AuditLog::query()->delete();
    $this->artisan('orders:activate-due')->assertSuccessful();
    expect((int) $user->fresh()->class)->toBe(3);      // 前置条件：真的改了

    expect(l09Page($this, $user->email))->toContain('暂无操作记录');
});

it('L09-4 「系统」这个署名，今天只可能来自被删掉的管理员', function () {
    // 先由一个真管理员留一条记录，然后把他删掉
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['class' => 0, 'money' => 0]);
    $this->actingAs($admin);
    $this->put("/admin/users/{$target->id}", [
        'class' => 2, 'transfer_enable_gb' => 50, 'money' => 0, 'original_money' => 0,
    ])->assertRedirect();

    $log = AuditLog::latest('id')->firstOrFail();
    expect($log->admin_id)->toBe($admin->id);
    $admin->delete();                                  // admin_id 变成悬空

    $html = l09Page($this, '');
    expect($html)->toContain('系统');

    // `[!]` 页面把 admin_id=null 渲染成「系统」。
    // 而 null 的两个来源里，自动任务那一支从来不写审计（L09-2/3），
    // 所以这个标签实际在说的是「这条记录的操作人已经不在了」。
    expect(AuditLog::whereNull('admin_id')->count())->toBeGreaterThan(0);
});
