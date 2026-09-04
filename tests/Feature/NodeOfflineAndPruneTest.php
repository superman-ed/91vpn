<?php

use App\Models\CrashLog;
use App\Models\DailyTraffic;
use App\Models\LoginLog;
use App\Models\Node;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// 运维任务:死节点自动离线 + 日志保留清理

it('把心跳失联的节点置离线,不动从未心跳的节点', function () {
    $now = now()->timestamp;
    $stale = Node::create(['name' => 'stale', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'a', 'online' => true, 'last_heartbeat' => $now - 600]);
    $fresh = Node::create(['name' => 'fresh', 'server' => 's', 'port' => 2, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'b', 'online' => true, 'last_heartbeat' => $now - 10]);
    $never = Node::create(['name' => 'never', 'server' => 's', 'port' => 3, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'c', 'online' => true, 'last_heartbeat' => 0]);

    $this->artisan('nodes:mark-offline', ['--seconds' => 180])->assertSuccessful();

    expect($stale->fresh()->online)->toBeFalse();  // 失联 → 离线
    expect($fresh->fresh()->online)->toBeTrue();    // 心跳新鲜 → 保持在线
    expect($never->fresh()->online)->toBeTrue();    // 从未心跳(=0) → 不动(由 enabled 控制)
});

it('按保留天数清理登录/崩溃/日流量日志', function () {
    $u = User::factory()->create();
    // created_at 会被 Eloquent 时间戳覆盖,故用 DB 直插精确设置旧/新时间
    $old = now()->subDays(400); $recent = now()->subDays(10);
    DB::table('login_logs')->insert([
        ['user_id' => $u->id, 'ip' => '1.1.1.1', 'created_at' => $old, 'updated_at' => $old],       // 旧
        ['user_id' => $u->id, 'ip' => '2.2.2.2', 'created_at' => $recent, 'updated_at' => $recent], // 新
    ]);
    DB::table('crash_logs')->insert([
        ['message' => 'old', 'fingerprint' => 'x', 'created_at' => $old, 'updated_at' => $old],
    ]);
    DailyTraffic::create(['user_id' => $u->id, 'date' => $old->toDateString(), 'u' => 1, 'd' => 1]);      // 旧(date 是真实列不被覆盖)
    DailyTraffic::create(['user_id' => $u->id, 'date' => $recent->toDateString(), 'u' => 1, 'd' => 1]);   // 新

    $this->artisan('logs:prune', ['--login-days' => 90, '--crash-days' => 180, '--traffic-days' => 365])->assertSuccessful();

    expect(LoginLog::count())->toBe(1);      // 只剩新的
    expect(CrashLog::count())->toBe(0);      // 旧崩溃已清
    expect(DailyTraffic::count())->toBe(1);  // 只剩新的
});
