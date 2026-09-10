<?php

use App\Models\Device;
use App\Models\EmailLog;
use App\Models\SubscribeLog;
use App\Models\User;
use App\Models\UserNotification;

/**
 * 只增不减的几张流水表的保留策略。
 *
 * `[!]` 造"旧数据"要在 create() 之后【直接改时间戳】：Eloquent 会在写入时用
 * 当前时间覆盖 created_at，把它塞进 create() 的数组里没有用 —— 第一版就这么写，
 * 三条用例全红，而失败信息只是"数量不对"，看不出是数据没造对还是清理没生效。
 *
 * `[!]` 这几张此前【没有任何清理】。单看行数都不大，但共同点是每次用户动作
 * 就写一行、永不回收 —— subscribe_logs 尤其：客户端每次刷新订阅就是一行，
 * 真实用户几小时刷一次，涨得比日志还快。
 *
 * 与 rule_alive_ip 是同一类问题（ROUND 第八段 ④）：建表时想到了，后来没人做。
 */
/** 把一行的 created_at 直接改老（绕开 Eloquent 的时间戳覆盖）。 */
function aged(\Illuminate\Database\Eloquent\Model $m, int $days): void
{
    $m->newQuery()->whereKey($m->getKey())
        ->update(['created_at' => now()->subDays($days)]);
}

it('订阅拉取记录按保留期清理', function () {
    $u = User::factory()->create();
    $old = SubscribeLog::create(['user_id' => $u->id, 'type' => 'clash', 'ip' => '1.1.1.1',
        'fetched_at' => now()->subDays(200)]);
    aged($old, 200);
    SubscribeLog::create(['user_id' => $u->id, 'type' => 'clash', 'ip' => '1.1.1.1',
        'fetched_at' => now()->subDays(3)]);

    $this->artisan('logs:prune')->assertExitCode(0);

    expect(SubscribeLog::count())->toBe(1);
});

// `[!!]` 未读的是用户还没看见的东西，按时间删掉等于替他把信扔了 —— 哪怕很旧。
it('站内通知只清已读的,未读永不删', function () {
    $u = User::factory()->create();
    aged(UserNotification::create(['user_id' => $u->id, 'title' => '旧的已读',
        'content' => 'x', 'read_at' => now()->subDays(200)]), 200);
    aged(UserNotification::create(['user_id' => $u->id, 'title' => '旧的未读',
        'content' => 'x', 'read_at' => null]), 200);

    $this->artisan('logs:prune')->assertExitCode(0);

    expect(UserNotification::count())->toBe(1);
    expect(UserNotification::first()->title)->toBe('旧的未读');
});

it('邮件记录按保留期清理', function () {
    aged(EmailLog::create(['to_email' => 'a@b.c', 'type' => 'verify', 'subject' => 's',
        'status' => 'ok']), 400);
    EmailLog::create(['to_email' => 'a@b.c', 'type' => 'verify', 'subject' => 's',
        'status' => 'ok']);

    $this->artisan('logs:prune')->assertExitCode(0);

    expect(EmailLog::count())->toBe(1);
});

// `[!]` 设备按 last_seen 而不是 created_at:一台天天在用的老设备不该因为
// 注册得早就被删。
it('设备按最后出现时间清理,不按注册时间', function () {
    $u = User::factory()->create();
    Device::create(['user_id' => $u->id, 'device_id' => 'old-but-active', 'platform' => 'ios',
        'created_at' => now()->subDays(500), 'last_seen' => now()->subHour()]);
    Device::create(['user_id' => $u->id, 'device_id' => 'long-gone', 'platform' => 'ios',
        'created_at' => now()->subDays(500), 'last_seen' => now()->subDays(400)]);

    $this->artisan('logs:prune')->assertExitCode(0);

    expect(Device::count())->toBe(1);
    expect(Device::first()->device_id)->toBe('old-but-active');
});
