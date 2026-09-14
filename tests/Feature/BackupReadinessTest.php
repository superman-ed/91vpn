<?php

use App\Console\Commands\RecordBackup;
use Illuminate\Support\Facades\Cache;

/**
 * 备份状态的呈现。
 *
 * `[!!]` 这组测试守的是【告警本身】。备份最危险的失败方式不是报错，是安静 ——
 * 宿主 crontab 里那条备 relaypanel 的任务就是活标本：
 * 每天照跑、每天失败、每天往日志里写一行，而那个应用早就停了，四天没人发现。
 * 告警逻辑写错，后果是"以为有备份"，比没有备份更糟。
 */
function brItem(): array
{
    foreach (app(\App\Services\ServiceReadiness::class)->check() as $c) {
        if ($c['title'] === '备份') {
            return $c;
        }
    }
    throw new RuntimeException('上线自检里没有「备份」这一项');
}

beforeEach(fn () => Cache::forget(RecordBackup::KEY));

it('从来没备份过时是红的，并说清后果', function () {
    $i = brItem();
    expect($i['level'])->toBe('bad');
    expect($i['detail'])->toContain('从来没有成功备份过');
    expect($i['fix'])->toContain('backup.sh');
});

it('刚备份成功是绿的，并带上文件名', function () {
    $this->artisan('backup:record', ['--status' => 'ok', '--detail' => '91vpn-x.tar.gz 96K'])
        ->assertSuccessful();

    $i = brItem();
    expect($i['level'])->toBe('ok');
    expect($i['detail'])->toContain('91vpn-x.tar.gz');
});

it('今天失败时是红的，且【仍然说得出上次成功是什么时候】', function () {
    $this->artisan('backup:record', ['--status' => 'ok', '--detail' => '好的那次'])->assertSuccessful();
    $this->travel(2)->days();
    $this->artisan('backup:record', ['--status' => 'fail', '--detail' => 'mysqldump 退出非零'])
        ->assertSuccessful();

    $i = brItem();
    expect($i['level'])->toBe('bad');
    expect($i['detail'])->toContain('mysqldump 退出非零');
    // `[!!]` 关键：失败【不能】把上次成功的时间戳冲掉。
    // 那个时间是"数据最远能恢复到哪"，是出事时第一个要问的数。
    expect($i['detail'])->toContain('48 小时');

    $this->travelBack();
});

it('cron 停了（没有失败记录，只是太久没成功）同样是红的，但说的是另一件事', function () {
    $this->artisan('backup:record', ['--status' => 'ok'])->assertSuccessful();
    $this->travel(2)->days();

    $i = brItem();
    expect($i['level'])->toBe('bad');
    expect($i['detail'])->toContain('cron 多半没在跑');
    // 与"执行了但失败"分开 —— 两者要查的地方不同
    expect($i['detail'])->not->toContain('最近一次备份失败');

    $this->travelBack();
});

it('略微超过一天只是黄的 —— 别为几小时的漂移制造红色噪声', function () {
    $this->artisan('backup:record', ['--status' => 'ok'])->assertSuccessful();
    $this->travel(28)->hours();

    expect(brItem()['level'])->toBe('warn');

    $this->travelBack();
});

it('判据是【距上次成功多久】，不是【上次执行成没成】', function () {
    // 连续失败三天，但今天碰巧成功了 —— 风险其实已经解除
    $this->artisan('backup:record', ['--status' => 'fail', '--detail' => '第一天'])->assertSuccessful();
    $this->artisan('backup:record', ['--status' => 'fail', '--detail' => '第二天'])->assertSuccessful();
    $this->artisan('backup:record', ['--status' => 'ok', '--detail' => '今天好了'])->assertSuccessful();

    expect(brItem()['level'])->toBe('ok');

    // 反过来：今天执行"成功"，但那是三天前记的 —— 风险是实打实的
    Cache::forever(RecordBackup::KEY, [
        'at' => time(), 'status' => 'ok', 'detail' => '',
        'last_ok_at' => time() - 3 * 86400,
    ]);
    expect(brItem()['level'])->toBe('bad');
});

it('「上线自检」里确实有备份这一项 —— 不然上面全是空转', function () {
    $titles = collect(app(\App\Services\ServiceReadiness::class)->check())->pluck('title');
    expect($titles)->toContain('备份');
});
