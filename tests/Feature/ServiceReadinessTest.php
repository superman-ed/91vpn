<?php

use App\Models\Node;
use App\Models\Plan;
use App\Models\User;
use App\Services\ServiceReadiness;

/**
 * 上线自检：一个新注册的用户，现在能不能真的用起来。
 *
 * `[!!]` 这些项单独都查得到，但缺哪一项的表现【都不是报错】：
 * 没配邮件用户卡在注册页（你看不到他）、没有 class=0 的节点新用户订阅是空的
 * （他只会说"连不上"）、没配支付要到他想买时才发现。都要等用户先撞上。
 */
function rd(): array
{
    $out = [];
    foreach (app(ServiceReadiness::class)->check() as $i) {
        $out[$i['title']] = $i;
    }

    return $out;
}

function rdNode(array $over = []): Node
{
    static $seq = 0;
    $seq++;

    return Node::create(array_merge([
        'name' => "n{$seq}", 'server' => "10.9.0.{$seq}", 'port' => 443, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => "RD{$seq}",
        'role' => 'landing', 'enabled' => true, 'online' => true,
    ], $over));
}

function rdPlan(): Plan
{
    return Plan::create([
        'name' => '月付', 'price' => 10, 'period' => 'month', 'transfer_gb' => 100,
        'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0, 'duration_days' => 30,
        'sort' => 0, 'on_sale' => true, 'stock' => -1,
    ]);
}

beforeEach(function () {
    config(['app.url' => 'https://app.example.com']);
});

it('什么都没配时把问题都列出来', function () {
    $r = rd();

    expect($r['套餐']['level'])->toBe('bad');
    expect($r['可用节点']['level'])->toBe('bad');
    expect($r['邮件']['level'])->toBe('bad');
});

// `[!!]` 这条是最隐蔽的一种:有节点、有套餐,但所有节点都设了等级门槛 ——
// 新注册用户等级是 0,他的订阅是空的,而他只会说"连不上"。
it('有节点但没有一个对新用户可见时,明确指出来', function () {
    rdNode(['node_class' => 3]);
    rdNode(['node_class' => 5]);

    $r = rd()['可用节点'];
    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('没有一个等级门槛是 0');
    expect($r['detail'])->toContain('新注册的用户等级是 0');
});

it('有 class=0 的节点时报 ok,并说清有几个对新用户可见', function () {
    rdNode(['node_class' => 0]);
    rdNode(['node_class' => 3]);

    $r = rd()['可用节点'];
    expect($r['level'])->toBe('ok');
    expect($r['detail'])->toContain('2 个在线')->toContain('1 个对新用户可见');
});

it('中转节点不算进可用节点', function () {
    rdNode(['role' => 'relay', 'port' => 0]);

    expect(rd()['可用节点']['level'])->toBe('bad');   // 只有中转 = 没有可用落地
});

// `[!!]` 没配邮件的后果最隐蔽:用户卡在注册页,而你这边什么日志异常都没有 ——
// 他根本没注册成功,不会出现在用户列表里。
it('没配邮件时说清"你这边看不到任何异常"', function () {
    $r = rd()['邮件'];

    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('收不到验证码');
    expect($r['detail'])->toContain('看不到任何异常');
});

it('没配支付只是 warn —— 免费节点仍然能用', function () {
    $r = rd()['支付'];

    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('买不了套餐');
});

// `[!]` 订阅链接由 APP_URL 派生。
it('APP_URL 还是 localhost 时报 bad', function () {
    config(['app.url' => 'http://localhost:8088']);

    $r = rd()['订阅地址'];
    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('客户端打不开');
});

// `[!!]` trycloudflare 的域名每次隧道重启都会变 —— 变了之后所有人的订阅
// 和所有节点会【同时】失效。这个雷值得单独报。
it('APP_URL 指向 trycloudflare 临时域名时报 warn', function () {
    config(['app.url' => 'https://abc-def.trycloudflare.com']);

    $r = rd()['订阅地址'];
    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('每次隧道重启都会变');
});

it('blockers 只数真正拦路的那几项', function () {
    rdNode();
    rdPlan();

    // 邮件没配 = 1 项 bad；支付只是 warn，不算
    expect(app(ServiceReadiness::class)->blockers())->toBe(1);
});

// `[!!]` 全绿时整块不显示 —— 一个常年绿着的横幅会被当成装饰,
// 等它变红的那天也没人注意。
it('首页:有问题时显示自检,全绿时不显示', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('上线自检');

    // 把所有 bad 都补齐
    rdNode();
    rdPlan();
    \App\Models\Setting::put('smtp_host', 'smtp.example.com');
    \App\Models\Setting::put('smtp_username', 'noreply@example.com');
    \App\Models\Setting::put('epay_pid', '123');
    \App\Models\Setting::put('epay_url', 'https://pay.example.com');

    $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('上线自检');
});
