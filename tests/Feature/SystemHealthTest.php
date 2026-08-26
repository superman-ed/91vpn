<?php

use App\Models\Node;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function healthAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('renders the health page with services and tasks', function () {
    $this->actingAs(healthAdmin())->get('/admin/system/health')
        ->assertOk()
        ->assertSee('系统健康')
        ->assertSee('定时任务')
        ->assertSee('MySQL 数据库')
        ->assertSee('Redis')
        ->assertViewHas('tasks')
        ->assertViewHas('services');
});

it('marks a fresh task heartbeat as ok and a stale one as bad', function () {
    Cache::forever('task_hb:stats:snapshot', ['at' => now()->timestamp, 'ok' => true]);         // 刚跑
    Cache::forever('task_hb:payment:reconcile', ['at' => now()->subHours(3)->timestamp, 'ok' => true]); // 超期(预期5min)

    $tasks = collect($this->actingAs(healthAdmin())->get('/admin/system/health')->viewData('tasks'));

    expect($tasks->firstWhere('sig', 'stats:snapshot')['status'])->toBe('ok');
    expect($tasks->firstWhere('sig', 'payment:reconcile')['status'])->toBe('bad');   // 3h > 5min*2.5
    // 从未运行的任务 = unknown
    expect($tasks->firstWhere('sig', 'traffic:reset-daily')['status'])->toBe('unknown');
});

it('records a heartbeat for a watched command via CommandFinished', function () {
    Cache::forget('task_hb:stats:snapshot');

    event(new \Illuminate\Console\Events\CommandFinished(
        'stats:snapshot', new \Symfony\Component\Console\Input\ArrayInput([]),
        new \Symfony\Component\Console\Output\NullOutput(), 0
    ));

    $hb = Cache::get('task_hb:stats:snapshot');
    expect($hb)->not->toBeNull();
    expect($hb['ok'])->toBeTrue();

    // 非监控命令不记录
    event(new \Illuminate\Console\Events\CommandFinished(
        'migrate', new \Symfony\Component\Console\Input\ArrayInput([]),
        new \Symfony\Component\Console\Output\NullOutput(), 0
    ));
    expect(Cache::get('task_hb:migrate'))->toBeNull();
});

it('shows node heartbeat status', function () {
    Node::create(['name' => 'HK-online', 'server' => 'x', 'port' => 1, 'secret' => 's', 'last_heartbeat' => now()->timestamp]);
    Node::create(['name' => 'HK-dead', 'server' => 'y', 'port' => 2, 'secret' => 't', 'last_heartbeat' => now()->subHour()->timestamp]);

    $nodes = collect($this->actingAs(healthAdmin())->get('/admin/system/health')->viewData('nodes'));
    expect($nodes->firstWhere('name', 'HK-online')['online'])->toBeTrue();
    expect($nodes->firstWhere('name', 'HK-dead')['online'])->toBeFalse();
});
