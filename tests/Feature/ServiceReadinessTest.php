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
    // `[!]` 邮件已降为 warn —— 它挡不住注册(注册走客户端、不碰邮箱)。
    // 真正该红的是客服入口:忘记密码【只能】靠它。
    expect($r['邮件']['level'])->toBe('warn');
    expect($r['客服入口']['level'])->toBe('bad');
    expect($r['备份']['level'])->toBe('bad');
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
// `[!!]` 这条原本断言"没配邮件 = bad,理由是用户收不到验证码"。
// 那个理由是【错的】,而且误导过一次上线判断:网页注册整个关掉了,
// 客户端注册只要用户名和密码。断言随之改掉,并钉住不许退回旧措辞。
it('没配邮件只是 warn,并且不再拿"注册收不到验证码"当理由', function () {
    $r = rd()['邮件'];

    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('不挡任何人');
    expect($r['detail'])->not->toContain('收不到验证码');
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

// `[!!]` 自检最坏的一种文案:照着做了还红着。原文在「订阅与面板同域」这一项里
//   写的建议是"去 .env 配 SUB_URL_BASE",可 SUB_URL_BASE 配好之后它照样 warn
//   —— 因为 sub.x.com 与 app.x.com 仍是同一个可注册域。这两条钉住:
//   配了就别再叫人去配,没配才叫。
it('订阅已配独立子域但仍同注册域时，不再叫人去配 SUB_URL_BASE', function () {
    config([
        'app.url' => 'https://app.example.com',
        'app.sub_url_base' => 'https://sub.example.com',
    ]);

    $r = rd()['订阅地址'];
    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('同一个可注册域');
    // 建议必须承认"已经配过了",而不是重复一句做完也不消失的指令
    expect($r['fix'])->toContain('已是独立子域');
    expect($r['fix'])->not->toContain('.env 里配 SUB_URL_BASE');
    // 并且要指明方向:搬订阅,不是搬面板(面板域名写死在已发出的客户端里)
    expect($r['fix'])->toContain('别反过来搬面板');
});

// `[!]` 这一条【在旧行为下也是绿的】(实测:注入旧文案后只有上面那条转红),
//   所以它不是对上面那次改动的反证,只是回归护栏 —— 防的是将来有人把
//   "没配"这个分支的建议一并删掉,导致真没配的人看不到该干什么。
it('订阅压根没配独立地址时，才叫人去配 SUB_URL_BASE', function () {
    config([
        'app.url' => 'https://app.example.com',
        'app.sub_url_base' => '',
    ]);

    $r = rd()['订阅地址'];
    expect($r['level'])->toBe('warn');
    expect($r['fix'])->toContain('SUB_URL_BASE');
    expect($r['fix'])->not->toContain('已是独立子域');
});

/** 假装刚成功备份过一次。测试库里没有这条记录，而"从来没备过"是 bad。 */
function rdBackupOk(): void
{
    \Cache::forever(\App\Console\Commands\RecordBackup::KEY, [
        'at' => now()->timestamp, 'status' => 'ok', 'detail' => 'test.tar.gz',
        'last_ok_at' => now()->timestamp,
    ]);
}

it('blockers 只数真正拦路的那几项', function () {
    rdNode();
    rdPlan();
    rdBackupOk();

    // 客服入口没配 = 1 项 bad；邮件与支付都只是 warn（支付未填配置），不算
    expect(app(ServiceReadiness::class)->blockers())->toBe(1);
});

// `[!!]` 单独钉住"没备份过"本身就是拦路项 —— 线上库丢了就没了，
// 这件事不该被别的绿灯衬托成小事。
it('从来没备份过时，blockers 会多算一项', function () {
    rdNode();
    rdPlan();
    \App\Models\Setting::put('support_tg', 'https://t.me/x');

    expect(app(ServiceReadiness::class)->blockers())->toBe(1);   // 只剩备份
    rdBackupOk();
    expect(app(ServiceReadiness::class)->blockers())->toBe(0);
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
    // `[!]` 定时任务那一项也要补上 —— 测试里缓存是空的，
    // 而"一条都没跑过"是 warn（新部署最常见的状态），会让卡片显示出来。
    foreach (\App\Providers\AppServiceProvider::WATCHED_TASKS as $sig) {
        \Cache::forever("task_hb:{$sig}", ['at' => now()->timestamp, 'ok' => true]);
    }
    rdBackupOk();   // 同理：测试里没有备份记录，而"从来没备过"是 bad
    // 订阅域名：与面板同域是 warn —— 全绿要求它单独一个域
    // `[!]` 这一项 2026-09-23 加入。理由与入口域名同源:订阅 URL 嵌在每个用户的
    //   客户端里,面板域名被封时订阅会一起失效,而改它要全员重新导入。
    config(['app.url' => 'https://app.example.com', 'app.sub_url_base' => 'https://sub.other.net']);

    // 入口域名：没配是 warn，配了同域是 bad —— 全绿要求配一个【不同注册域】的
    $edNode = rdNode();
    \App\Models\EntryDomain::create([
        'domain' => 'entry.some-other-domain.com', 'node_id' => $edNode->id, 'status' => 'active',
    ]);
    \App\Models\Setting::put('support_tg', 'https://t.me/x');   // 忘密码的唯一出路
    // `[!]` 支付现在按证据判：光填配置不算，要有一笔带网关交易号的支付
    $payer = User::factory()->create();
    \App\Models\Order::create([
        'user_id' => $payer->id, 'plan_id' => rdPlan()->id, 'amount' => 30,
        'status' => 'paid', 'period' => 'month', 'order_no' => 'RD-GREEN',
        'pay_method' => 'epay', 'trade_no' => 'GW-GREEN-1', 'paid_at' => now(),
    ]);

    $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('上线自检');
});

// ─────────────────────────────────────────────────────────────────
// 支付：按【证据】判，不按字段非空判
// ─────────────────────────────────────────────────────────────────
it('填了配置但从来没有过网关交易号时，支付是红的', function () {
    \App\Models\Setting::put('epay_pid', '123');
    \App\Models\Setting::put('epay_url', 'https://pay.example.com');

    $r = rd()['支付'];
    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('字段非空不等于对接完成');
});

it('有过一笔带网关交易号的支付后转绿', function () {
    \App\Models\Setting::put('epay_pid', '123');
    \App\Models\Setting::put('epay_url', 'https://pay.example.com');
    $u = User::factory()->create();
    $p = rdPlan();
    \App\Models\Order::create([
        'user_id' => $u->id, 'plan_id' => $p->id, 'amount' => 30,
        'status' => 'paid', 'period' => 'month', 'order_no' => 'RD-T1',
        'pay_method' => 'epay', 'trade_no' => 'GW-20260914-0001', 'paid_at' => now(),
    ]);

    expect(rd()['支付']['level'])->toBe('ok');
});

it('`pay_method=epay` 但没有交易号的单【不算数】—— 那是能被造出来的', function () {
    \App\Models\Setting::put('epay_pid', '123');
    \App\Models\Setting::put('epay_url', 'https://pay.example.com');
    $u = User::factory()->create();
    $p = rdPlan();
    \App\Models\Order::create([
        'user_id' => $u->id, 'plan_id' => $p->id, 'amount' => 30,
        'status' => 'paid', 'period' => 'month', 'order_no' => 'RD-T2',
        'pay_method' => 'epay', 'trade_no' => null, 'paid_at' => now(),
    ]);

    // `[!!]` 线上就有 48 笔这样的假单(seeder 造的),带 trade_no 的 0 笔。
    // 判据认 trade_no 而不认 pay_method,正是为了不被它们骗过去。
    expect(rd()['支付']['level'])->toBe('bad');
});

// ─────────────────────────────────────────────────────────────────
// 客服入口：忘记密码的唯一出路
// ─────────────────────────────────────────────────────────────────
it('一个客服渠道都没配时是红的 —— 忘密码的人无处可去', function () {
    $r = rd()['客服入口'];
    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('没有邮箱找回');
});

it('配了 Telegram 就够得着', function () {
    \App\Models\Setting::put('support_tg', 'https://t.me/x');
    expect(rd()['客服入口']['level'])->toBe('ok');
});

it('只配工单不算 —— 工单要登录，而站在这里的人正是登不进去的那个', function () {
    // 工单不是可配项，这里验的是"没有任何未登录可达渠道时仍然是红的"
    \App\Models\Setting::put('smtp_host', 'smtp.example.com');   // 配点别的
    expect(rd()['客服入口']['level'])->toBe('bad');
});

it('登录页给得出联系方式，而不是只写一句"请联系客服"', function () {
    \App\Models\Setting::put('support_tg', 'https://t.me/mysupport');

    $html = $this->get('/login')->assertOk()->getContent();
    expect($html)->toContain('https://t.me/mysupport');
    // 客服挂件也要出现在未登录页上
    expect($html)->toContain('联系客服');
});

it('未登录时客服面板不给「提交工单」—— 那是个死循环', function () {
    \App\Models\Setting::put('support_tg', 'https://t.me/mysupport');

    $guest = $this->get('/login')->assertOk()->getContent();
    expect($guest)->not->toContain('/user/ticket');

    // 对照：登录后是给的
    $u = User::factory()->create();
    $in = $this->actingAs($u)->get('/user')->assertOk()->getContent();
    expect($in)->toContain('/user/ticket');
});

it('邮件没配只是 warn —— 它挡不住注册（注册走客户端、不碰邮箱）', function () {
    $r = rd()['邮件'];
    expect($r['level'])->toBe('warn');
    expect($r['detail'])->toContain('不挡任何人');
});

// ─────────────────────────────────────────────────────────────────
// 入口域名分离：CNAME 标签也算在内
//
// `[!!]` 门牌换成了别的域名、标签还留在面板主域上 —— 等于没分离。
// 封的是【可注册域】，整条 CNAME 链一起死，而那时你连后台都进不去。
// ─────────────────────────────────────────────────────────────────
it('门牌不同域但 CNAME 标签落在面板主域上，仍然判红', function () {
    $panel = parse_url((string) config('app.url'), PHP_URL_HOST);
    $reg = implode('.', array_slice(explode('.', (string) $panel), -2));

    \App\Models\EntryDomain::create([
        'domain' => 'entry.some-other-domain.com',
        'cname_target' => 'label.'.$reg,          // ← 标签回到了面板主域
        'node_id' => rdNode()->id,
        'status' => 'active',
    ]);

    $r = rd()['入口域名'];
    expect($r['level'])->toBe('bad');
    expect($r['detail'])->toContain('label.'.$reg);
});

it('门牌与标签都在面板主域之外时判绿', function () {
    \App\Models\EntryDomain::create([
        'domain' => 'entry.some-other-domain.com',
        'cname_target' => 'label.yet-another-domain.net',
        'node_id' => rdNode()->id,
        'status' => 'active',
    ]);

    expect(rd()['入口域名']['level'])->toBe('ok');
});
