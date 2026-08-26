<?php

use App\Models\User;
use App\Models\UserNotification;

function notiAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('sends a single notification to a user by email', function () {
    $u = User::factory()->create(['email' => 'target@test.local']);

    $this->actingAs(notiAdmin())->post('/admin/notifications', [
        'mode' => 'single', 'email' => 'target@test.local',
        'title' => '你好', 'content' => '这是一条测试', 'type' => 'system',
    ])->assertRedirect();

    $n = UserNotification::where('user_id', $u->id)->first();
    expect($n->title)->toBe('你好');
    expect($n->read_at)->toBeNull();
});

it('broadcasts to a segment (expiring members)', function () {
    // 2 个 7 天内到期会员 + 1 个免费用户
    User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(3), 'banned' => false]);
    User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(6), 'banned' => false]);
    User::factory()->create(['class' => 0, 'banned' => false]);

    $this->actingAs(notiAdmin())->post('/admin/notifications', [
        'mode' => 'batch', 'segment' => 'expiring',
        'title' => '续费提醒', 'content' => '快到期了', 'type' => 'notice',
    ])->assertRedirect();

    expect(UserNotification::where('title', '续费提醒')->count())->toBe(2);   // 只发给 2 个即将到期的
});

it('auto expiry command notifies members due within 3 days, deduped', function () {
    $u = User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(2), 'banned' => false]);
    User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(10), 'banned' => false]); // 太远,不发

    $this->artisan('notify:expiry')->assertSuccessful();
    expect(UserNotification::where('type', 'expiry')->count())->toBe(1);
    expect(UserNotification::where('user_id', $u->id)->where('type', 'expiry')->exists())->toBeTrue();

    // 再跑一次:近4天已发过 → 去重,不重复
    $this->artisan('notify:expiry');
    expect(UserNotification::where('type', 'expiry')->count())->toBe(1);
});

it('lets a user read their notifications and updates unread count', function () {
    $u = User::factory()->create();
    $n = UserNotification::create(['user_id' => $u->id, 'title' => 'x', 'content' => 'y', 'type' => 'system']);
    expect($u->unreadNotificationCount())->toBe(1);

    $this->actingAs($u)->get('/user/messages')->assertOk()->assertSee('x');
    $this->actingAs($u)->post("/user/messages/{$n->id}/read")->assertRedirect();

    expect($n->fresh()->read_at)->not->toBeNull();
    expect($u->fresh()->unreadNotificationCount())->toBe(0);
});

it('blocks reading another user notification', function () {
    $other = User::factory()->create();
    $n = UserNotification::create(['user_id' => $other->id, 'title' => 'x', 'content' => 'y', 'type' => 'system']);

    $this->actingAs(User::factory()->create())->post("/user/messages/{$n->id}/read")->assertForbidden();
});

it('shows the bell dropdown with recent notifications on user pages', function () {
    $u = User::factory()->create();
    UserNotification::create(['user_id' => $u->id, 'title' => '铃铛测试消息', 'content' => 'hi', 'type' => 'notice']);

    $this->actingAs($u)->get('/user')
        ->assertOk()
        ->assertSee('notiPanel', false)        // 下拉面板已渲染
        ->assertSee('铃铛测试消息')             // 最近消息进下拉
        ->assertSee('查看全部消息');
});
