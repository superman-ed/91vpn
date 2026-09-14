<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

/**
 * 操作日志页 —— 消费端守卫。
 *
 * `[!!]` 这组用例最初是【审计实验】（L-09），断言的是"定时任务改了用户权益，
 * 日志页上一个字都没有"。缺口已补（见 SystemAuditTrailTest 与
 * AuditLog::SYSTEM_ACTIONS），断言随之反转。
 *
 * 判据始终落在【页面】上，不是 AuditLog::count() ——
 * 人真正会做的动作是打开 /admin/system/audit 按用户搜一下。
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

it('L09-1 人在后台改了用户，日志页上查得到', function () {
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

it('L09-2 定时任务抹掉用户已付费的流量包，按用户搜得到那一条', function () {
    $gb = 1024 ** 3;
    $user = User::factory()->create(['money' => 100]);
    // 走真实购买,确保这是一次真的付费行为
    foreach ([l09Plan(), l09Plan(['transfer_gb' => 50, 'is_data_pack' => true, 'class' => 0])] as $p) {
        $this->actingAs($user->fresh());
        $this->post('/user/order/create', ['plan_id' => $p->id])->assertRedirect();
        $o = Order::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->post("/user/order/{$o->id}/pay-balance")->assertRedirect();
    }
    expect((int) $user->fresh()->transfer_enable)->toBe(150 * $gb);

    AuditLog::query()->delete();                       // 只看这一步产生了什么
    $user->fresh()->update(['next_reset_at' => now()->subMinute()]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();
    expect((int) $user->fresh()->transfer_enable)->toBe(100 * $gb);   // 前置条件

    // `[!!]` 消费端：客服/运营按这个用户搜
    $html = l09Page($this, $user->email);
    expect($html)->not->toContain('暂无操作记录');
    expect($html)->toContain('流量重置');
    expect($html)->toContain('50.00 GB');              // 抹掉了多少,答得上
});

it('L09-3 自动发货把用户等级从 0 改到 3，也查得到', function () {
    $user = User::factory()->create(['class' => 0]);
    Order::create([
        'user_id' => $user->id, 'plan_id' => l09Plan()->id, 'amount' => 10,
        'status' => 'queued', 'period' => 'month', 'order_no' => 'L09Q',
        'activate_at' => now()->subMinute(),
    ]);

    AuditLog::query()->delete();
    $this->artisan('orders:activate-due')->assertSuccessful();
    expect((int) $user->fresh()->class)->toBe(3);      // 前置条件

    $html = l09Page($this, 'L09Q');
    expect($html)->not->toContain('暂无操作记录');
    expect($html)->toContain('自动发货');
});

it('L09-4 「系统」这个标签只给定时任务，管理员被删了是另一句话', function () {
    // (a) 定时任务写的
    $user = User::factory()->create([
        'class' => 3, 'class_expire' => now()->addYear(),
        'transfer_enable' => 150 * 1024 ** 3, 'base_transfer_enable' => 100 * 1024 ** 3,
        'u' => 0, 'd' => 0, 'next_reset_at' => now()->subMinute(),
    ]);
    $this->artisan('traffic:reset-monthly')->assertSuccessful();
    $sysLog = AuditLog::where('action', 'user.traffic_reset')->latest('id')->first();
    expect($sysLog)->not->toBeNull();
    expect($sysLog->isSystem())->toBeTrue();

    // (b) 人工写的，然后把那个管理员删掉 —— admin_id 同样变成 null
    $admin = User::factory()->create(['is_admin' => true]);
    $victim = User::factory()->create(['class' => 0, 'money' => 0]);
    $this->actingAs($admin);
    $this->put("/admin/users/{$victim->id}", [
        'class' => 1, 'transfer_enable_gb' => 10, 'money' => 0, 'original_money' => 0,
    ])->assertRedirect();
    $this->get('/admin/users');            // 消费掉 flash,否则它会渲染到下一页上
    $humanLog = AuditLog::where('action', 'user.update')->latest('id')->first();
    $admin->delete();
    expect($humanLog->fresh()->admin_id)->toBeNull();
    expect($humanLog->fresh()->isSystem())->toBeFalse();

    // `[!!]` 两条记录的 admin_id 都是 null,而含义相反 —— 页面必须分得开。
    $html = l09Page($this, '');
    expect($html)->toContain('已删除的管理员');
    expect($html)->toContain('流量重置');
});
