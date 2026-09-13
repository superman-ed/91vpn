<?php

use App\Http\Controllers\Admin\HealthController;
use App\Providers\AppServiceProvider;

/**
 * 收口审计 ·「记账与现实脱节」这一类里最要害的一条：**谁看着看门人**。
 *
 * `[!!]` 定时任务是所有记账保鲜的唯一来源。一条任务停了，它维护的那份记录
 * 就会永远停在最后一次的值上 —— 而那份记录看起来【完全正常】。
 * 最典型的是 nodes:mark-offline：它停了，所有节点永远显示在线，
 * 死节点照样被合成进订阅，页面上一切正常。
 *
 * 此前两份名单（记录用的 WATCHED_TASKS、展示用的 HealthController::TASKS）
 * 都靠人手同步，而且都不是从真实调度派生的 —— 新加一条任务，
 * 它就默默地不在监视之内。2026-09-13 实际漏了三条。
 */

/** 从 routes/console.php 里抽出真实排的那些任务。 */
function stScheduled(): array
{
    $src = file_get_contents(base_path('routes/console.php'));
    preg_match_all("#Schedule::command\(\s*'([a-z0-9:_-]+)'#i", $src, $m);

    return array_values(array_unique($m[1]));
}

it('每一条排了的定时任务都在监视名单里', function () {
    $missing = array_diff(stScheduled(), AppServiceProvider::WATCHED_TASKS);

    expect(array_values($missing))->toBe([],
        '这些任务排了但没记心跳 —— 它们停了之后没有任何地方会察觉');
});

it('每一条排了的定时任务都在健康页上列出来', function () {
    $listed = array_keys((new ReflectionClass(HealthController::class))->getConstant('TASKS'));
    $missing = array_diff(stScheduled(), $listed);

    expect(array_values($missing))->toBe([],
        '这些任务记了心跳但健康页不显示 —— 记了没人看等于没记');
});

it('名单里没有已经不排了的任务 —— 否则会一直显示"从未运行"', function () {
    // `[!]` 反方向同样要查：一条删掉的任务留在名单里，
    // 健康页会永远显示它"未知"，而人会慢慢学会忽略那一行 ——
    // 于是真正停掉的那条也一起被忽略了。
    $scheduled = stScheduled();
    $listed = array_keys((new ReflectionClass(HealthController::class))->getConstant('TASKS'));

    expect(array_values(array_diff(AppServiceProvider::WATCHED_TASKS, $scheduled)))->toBe([]);
    expect(array_values(array_diff($listed, $scheduled)))->toBe([]);
});

it('`[!!]` 健康页判过期的阈值要与真实排期同量级', function () {
    // 阈值写错的后果是两头坏：
    //   写得太小 → 天天误报，人很快学会忽略整页；
    //   写得太大 → 任务停了很久都不报，等于没监视。
    $src = file_get_contents(base_path('routes/console.php'));
    $tasks = (new ReflectionClass(HealthController::class))->getConstant('TASKS');

    // 从调度写法推出大致周期（秒）
    $byMethod = [
        'everyMinute' => 60, 'everyFiveMinutes' => 300, 'everyTenMinutes' => 600,
        'hourly' => 3600, 'dailyAt' => 86400, 'daily' => 86400,
    ];
    $bad = [];
    foreach ($tasks as $sig => [$name, $interval, $freq]) {
        if (! preg_match("#Schedule::command\(\s*'".preg_quote($sig, '#')."'\s*\)\s*->\s*([A-Za-z]+)#", $src, $m)) {
            continue;
        }
        $expected = $byMethod[$m[1]] ?? null;
        if ($expected !== null && $interval !== $expected) {
            $bad[] = "{$sig}：健康页按 {$interval}s 判，实际排期 {$m[1]}（{$expected}s）";
        }
    }

    expect($bad)->toBe([]);
});

/**
 * 总览上要主动说一句。`[!!]` 只有健康页显示的话，调度器停了没人会发现 ——
 * 那一页要主动打开，而人只在已经怀疑出事时才会去开它。
 */
function stReadiness(): array
{
    return collect(app(\App\Services\ServiceReadiness::class)->check())
        ->firstWhere('title', '定时任务');
}

it('一条都没跑过时说"调度器多半没起来"，级别是 warn 不是 bad', function () {
    // `[!]` 新部署最常见的就是这个状态。判成 bad 会让第一分钟满屏红，
    // 而人会学会忽略整张卡片 —— 那时真出事也一起被忽略了。
    \Cache::flush();

    $r = stReadiness();
    expect($r['level'])->toBe('warn')
        ->and($r['detail'])->toContain('调度器多半没起来');
});

it('跑过又停了才算 bad —— 那是真的出事了', function () {
    \Cache::flush();
    foreach (\App\Providers\AppServiceProvider::WATCHED_TASKS as $sig) {
        \Cache::forever("task_hb:{$sig}", ['at' => now()->timestamp, 'ok' => true]);
    }
    // 让其中一条变陈旧
    \Cache::forever('task_hb:nodes:mark-offline',
        ['at' => now()->subDays(3)->timestamp, 'ok' => true]);

    $r = stReadiness();
    expect($r['level'])->toBe('bad')
        ->and($r['detail'])->toContain('nodes:mark-offline')
        // 要说清后果，不能只说"任务停了"
        ->and($r['detail'])->toContain('停在最后一次的值上');
});

it('都在跑时是 ok —— 卡片会整块隐藏，不制造常驻噪声', function () {
    \Cache::flush();
    foreach (\App\Providers\AppServiceProvider::WATCHED_TASKS as $sig) {
        \Cache::forever("task_hb:{$sig}", ['at' => now()->timestamp, 'ok' => true]);
    }

    expect(stReadiness()['level'])->toBe('ok');
});
