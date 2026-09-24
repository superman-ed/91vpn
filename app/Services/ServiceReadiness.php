<?php

namespace App\Services;

use App\Console\Commands\RecordBackup;
use App\Models\EntryDomain;
use App\Models\Node;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Recharge;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 上线自检：一个新注册的用户，现在能不能真的用起来。
 *
 * [!!] 这个判断此前【没有任何页面回答】。每一项单独去查都能查到
 * （套餐页、节点页、设置页），但"合起来够不够开张"没人说 ——
 * 而缺哪一项的表现都不是报错：
 *   - 没配邮件 → 用户注册收不到验证码，卡在注册页，你什么都看不到
 *   - 没有 class=0 的节点 → 新用户订阅是空的，以为服务坏了
 *   - 没配支付 → 他想买的时候才发现付不了
 * 都要等用户先撞上，你才从工单里知道。
 *
 * [!] 每一项都给出"去哪修"，而不是只报一个红叉。
 */
class ServiceReadiness
{
    /**
     * @return array<int,array{level:string,title:string,detail:string,fix:?string}>
     *                                                                               level: ok | warn | bad
     */
    public function check(): array
    {
        return [
            $this->plans(),
            $this->freeNode(),
            $this->mail(),
            $this->payment(),
            $this->subUrl(),
            $this->scheduler(),
            $this->backup(),
            $this->support(),
            $this->entryDomainSeparation(),
        ];
    }

    /**
     * 入口域名与面板域名是不是分属不同的可注册域（D-4）。
     *
     * `[!!]` 域名污染与封禁通常作用在【整个可注册域】上。
     * entry.91app.shop 与 app.91app.shop 同属 91app.shop —— 主域被打时
     * 面板与入口一起死，而那时你连后台都进不去，没法改任何东西。
     *
     * 更要命的是反方向：入口域名被封是【常态、预期内】的事件，
     * 你本来就准备好了换 A 记录；但若它与面板同域，
     * 一次例行的封锁就会把后台一起带走。
     */
    private function entryDomainSeparation(): array
    {
        $panel = $this->registrable((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($panel === '') {
            return $this->x('warn', '入口域名', 'APP_URL 解析不出主机名，无法比对', null);
        }

        // `[!!]` CNAME 标签也要查。门牌换了域名、标签还留在面板主域上，
        // 等于没分离 —— 封的是【可注册域】，整条 CNAME 链一起死。
        $offenders = [];
        foreach (EntryDomain::where('status', 'active')->get() as $d) {
            foreach (array_filter([(string) $d->domain, (string) $d->cname_target]) as $host) {
                if ($this->registrable($host) === $panel) {
                    $offenders[] = $host;
                }
            }
        }
        $offenders = array_values(array_unique($offenders));

        if ($offenders !== []) {
            return $this->x('bad', '入口域名',
                '入口域名 '.implode('、', $offenders)
                ."与面板同属 {$panel} —— 主域被封时面板和入口一起死，"
                .'而那时你进不了后台、改不了任何东西。换一个单独注册的域名'
                .'（CNAME 标签也算在内：封的是整个可注册域）',
                '/admin/entry-domains');
        }

        $n = EntryDomain::where('status', 'active')->count();

        return $n === 0
            ? $this->x('warn', '入口域名', '还没有启用中的入口域名 —— 订阅目前发的是节点裸 IP，'
                .'IP 被封只能重发订阅并等客户端更新（默认 24 小时）', '/admin/entry-domains')
            : $this->x('ok', '入口域名', "{$n} 个启用中，且与面板域名分开");
    }

    /** 取可注册域（最后两段）。够用即可：这里只判"是不是同一个域"。 */
    private function registrable(string $host): string
    {
        $p = array_filter(explode('.', strtolower(trim($host))));

        return count($p) >= 2 ? implode('.', array_slice($p, -2)) : implode('.', $p);
    }

    /**
     * 忘记密码的人，有没有一条够得着的路。
     *
     * `[!!]` 这一项之所以是【拦路项】而不是锦上添花：
     * 本产品的注册走客户端、只要用户名和密码，邮箱是 `@invalid.local` 占位或为空；
     * 而 Auth\PasswordController 是没有路由的死代码 —— 也就是说
     * **邮箱找回这条路根本不存在**。产品上选定的找回方式就是"联系客服"。
     * 客服联系方式没配，等于这批用户一旦忘了密码就永久锁死，
     * 而你这边只会看到一个再也不登录的账号。
     *
     * `[!]` 判据只认【未登录也够得着】的渠道。工单不算：它要登录，
     * 而站在这个场景里的人正是登不进去的那个。
     */
    private function support(): array
    {
        $reachable = array_filter([
            'Crisp' => (string) setting('crisp_website_id', ''),
            '第三方客服代码' => (string) setting('support_widget', ''),
            'Telegram 客服' => (string) setting('support_tg', ''),
            '客服群' => (string) setting('support_group', ''),
        ], fn ($v) => $v !== '');

        if (! $reachable) {
            return $this->x('bad', '客服入口',
                '一个都没配 —— 而忘记密码【只能】靠联系客服（本产品没有邮箱找回）。'
                .'现在这批用户一旦忘密码就永久锁死，而你只会看到一个再也不登录的账号',
                '/admin/settings');
        }

        return $this->x('ok', '客服入口',
            '已配 '.implode('、', array_keys($reachable)).'（登录页与注册页都够得着）');
    }

    /**
     * 上一次备份是什么时候、成没成。
     *
     * `[!!]` 这一项存在的理由，是宿主 crontab 里那条备 relaypanel 的任务：
     * 它每天照跑、每天失败、每天往日志里写一行，而 relaypanel 的容器
     * 早就退出了 —— 四天没人发现。备份最危险的失败方式不是报错，是【安静】。
     *
     * `[!]` 判据用「距上次成功多久」，不是「上次执行成没成」：
     * 连续失败三天但今天碰巧成功了，风险是小的；
     * 今天执行成功但那是三天前的数据，风险是大的。要答的是后一个问题。
     */
    private function backup(): array
    {
        $hb = Cache::get(
            RecordBackup::KEY, []
        );
        $lastOk = $hb['last_ok_at'] ?? null;

        if (! $lastOk) {
            return $this->x('bad', '备份', '从来没有成功备份过 —— 线上库现在丢了就没了',
                'bash tools/backup.sh --install-cron');
        }

        // `[!]` 用 now() 不用 time()：time() 是真实时钟，不受 Carbon::setTestNow 影响，
        // 时间相关的判据就没法写测试。本项目在 LayerHealth 上已经栽过一次
        // （判据见 ROUND-2026-09 P1-2），这里不重复。
        $ageH = (int) floor((now()->timestamp - (int) $lastOk) / 3600);
        $when = Carbon::createFromTimestamp($lastOk)->format('m-d H:i');

        if (($hb['status'] ?? 'ok') === 'fail') {
            return $this->x('bad', '备份',
                "最近一次备份失败（{$hb['detail']}）；上次成功是 {$when}，已过 {$ageH} 小时",
                'bash tools/backup.sh   # 手工跑一次看报什么');
        }
        if ($ageH >= 36) {
            return $this->x('bad', '备份', "上次成功备份是 {$when}，已过 {$ageH} 小时 —— cron 多半没在跑",
                'crontab -l | grep backup.sh');
        }
        if ($ageH >= 26) {
            return $this->x('warn', '备份', "上次成功备份 {$when}（{$ageH} 小时前），比每日一次略久", null);
        }

        return $this->x('ok', '备份', "上次成功 {$when}".($hb['detail'] ? "（{$hb['detail']}）" : ''));
    }

    /**
     * 定时任务到底在不在跑。
     *
     * `[!!]` 此前只有 /admin/system/health 那一页显示它,而那一页要主动打开 ——
     * 调度器停了没有任何地方会主动说一句。而它是【所有记账保鲜的唯一来源】:
     * nodes:mark-offline 停了,所有节点永远显示在线,死节点照样进订阅。
     *
     * `[!]` 两档分开,为的是别制造噪声:
     *   全部从未跑过 → 多半是调度器压根没起来(新部署最常见),warn
     *   跑过又停了   → 那是真的出事了,bad
     * 不区分的话,新部署第一分钟就会满屏红,而人会学会忽略这张卡片。
     */
    private function scheduler(): array
    {
        $tasks = AppServiceProvider::WATCHED_TASKS;
        $never = $stale = [];
        foreach ($tasks as $sig) {
            $hb = Cache::get("task_hb:{$sig}");
            if (! isset($hb['at'])) {
                $never[] = $sig;

                continue;
            }
            // `[!]` 阈值按【最疏的那条】给,这里只答"调度器还活着吗",
            // 每条任务各自的过期判定在健康页上。取 1 天 + 富余。
            if (now()->timestamp - $hb['at'] > 90000) {
                $stale[] = $sig;
            }
        }

        if (count($never) === count($tasks)) {
            return $this->x('warn', '定时任务', '一条都没运行过 —— 调度器多半没起来。'
                .'流量清零、订单激活、失联节点置离线都靠它。',
                'docker compose ps scheduler');
        }
        if ($stale !== []) {
            return $this->x('bad', '定时任务',
                '这些任务跑过、但已经很久没再跑了：'.implode('、', $stale)
                .'。它们维护的记录会停在最后一次的值上，而页面上看不出来。',
                '看 /admin/system/health 的定时任务一栏');
        }

        return $this->x('ok', '定时任务', '在跑');
    }

    /** 还剩几项没配好（只数 bad）。 */
    public function blockers(): int
    {
        return count(array_filter($this->check(), fn ($i) => $i['level'] === 'bad'));
    }

    private function plans(): array
    {
        $n = Plan::count();

        return $n > 0
            ? $this->x('ok', '套餐', "{$n} 个")
            : $this->x('bad', '套餐', '一个套餐都没有 —— 用户看到的是空商店', '/admin/plans');
    }

    /**
     * [!!] 新注册用户的等级是 0，只能看到 node_class=0 的节点。
     * 有节点、有套餐，但所有节点都设了门槛的话，新用户的订阅是【空的】——
     * 而他看到的只是"连不上"，不会来告诉你"我的订阅是空的"。
     */
    private function freeNode(): array
    {
        $usable = Node::userVisible()->where('enabled', true)->where('online', true);
        $total = (clone $usable)->count();
        $free = (clone $usable)->where('node_class', 0)->count();

        if ($total === 0) {
            return $this->x('bad', '可用节点', '没有在线的落地节点 —— 谁的订阅里都是空的', '/admin/nodes');
        }
        if ($free === 0) {
            return $this->x('bad', '可用节点',
                "有 {$total} 个在线节点，但【没有一个等级门槛是 0】—— "
                .'新注册的用户等级是 0，他们的订阅会是空的',
                '/admin/nodes');
        }

        return $this->x('ok', '可用节点', "{$total} 个在线，其中 {$free} 个对新用户可见");
    }

    /**
     * [!!] 没配邮件的后果最隐蔽：用户卡在注册页收不到验证码，
     * 而你这边【什么日志都不会有异常】—— 他根本没注册成功，不会出现在用户列表里。
     */
    /**
     * SMTP。
     *
     * `[!!]` 这一项曾经是 bad，理由写的是"用户注册收不到验证码，会卡在注册页"——
     * **那个理由是错的**，而且它误导过一次上线判断。实际：
     *   · 网页注册整个关掉了（RegisterController@store 直接回"请在客户端中注册"）
     *   · 客户端注册（POST /api/auth/register）只要用户名和密码，根本不碰邮箱
     *   · Auth\EmailCodeController 与 Auth\PasswordController 都是【没有路由】的死代码
     * 所以今天没有任何面向用户的功能依赖邮件，它挡不住任何人。
     *
     * 降到 warn 而不是直接去掉：邮件一旦要用（找回密码、到期提醒改走邮件），
     * 没配就是静默失败，那时候这一项要在。
     */
    private function mail(): array
    {
        $host = (string) setting('smtp_host', '');
        $user = (string) setting('smtp_username', '');

        return $host !== '' && $user !== ''
            ? $this->x('ok', '邮件', "已配（{$host}）")
            : $this->x('warn', '邮件',
                '没配 SMTP。当前不挡任何人 —— 注册走客户端、只要用户名和密码，'
                .'邮箱找回那条路本来就不存在（忘密码走客服）。'
                .'将来要做邮件找回或邮件通知之前需要配上', '/admin/settings');
    }

    /**
     * 支付到底能不能收到钱。
     *
     * `[!!]` 这一项曾经只检查 `epay_pid` / `epay_url` 非空就报 ok ——
     * **而字段非空不等于对接完成**。它一路显示着绿灯，而实际上
     * epay 从未对接、系统里没有任何可用的收款路径。
     * 我自己也被这个绿灯骗过一次，据此判断"关掉 mock 支付不影响正常功能"。
     *
     * 现在改成【按证据判】：看有没有过一笔带**网关交易号**的成功支付。
     * trade_no 只能来自网关的真实回调，造不出来 ——
     * 而 `pay_method='epay'` 是可以被 seeder 造出来的（线上就有 48 笔这样的假单，
     * 带 trade_no 的 0 笔）。
     *
     * `[!]` 这一项会一直红到你走通第一笔真实支付为止 —— 那是对的。
     * 一个从来没收到过钱的收款通道，没有任何理由被当成可用。
     */
    private function payment(): array
    {
        if ((string) setting('epay_pid', '') === '' || (string) setting('epay_url', '') === '') {
            return $this->x('warn', '支付', '没配支付 —— 用户能注册能用免费节点，但买不了套餐',
                '/admin/settings');
        }

        $real = Order::where('status', 'paid')->whereNotNull('trade_no')->exists()
            || Recharge::where('status', 'paid')->whereNotNull('trade_no')->exists();

        return $real
            ? $this->x('ok', '支付', '已对接，且有过带网关交易号的真实支付')
            : $this->x('bad', '支付',
                '配置已填，但【从未有过一笔带网关交易号的支付】—— 字段非空不等于对接完成。'
                .'走通第一笔真实小额支付后这一项会自动转绿',
                '/admin/settings');
    }

    /**
     * [!] 订阅链接:优先 SUB_URL_BASE,留空则由 APP_URL 派生。
     * 填成 localhost 的话,发出去的订阅客户端打不开。
     */
    private function subUrl(): array
    {
        // `[!!]` 这里有【两件事】,不能混:
        //   · APP_URL 本身是否可用 —— 它不只用于订阅,邀请链接/邮件/跳转都用它,
        //     所以即便订阅另配了域名,APP_URL 是 localhost 仍然是故障。
        //   · 订阅地址是否与面板分开 —— 那是另一回事,见本方法末尾。
        // `[D]` 加 SUB_URL_BASE 时我把整项改成只读订阅 URL,结果 APP_URL=localhost
        //   不再报警 —— 被 ServiceReadinessTest 两条用例当场抓到。
        $panel = (string) config('app.url');
        $base = rtrim((string) config('app.sub_url_base'), '/');
        $url = $base !== '' ? $base : $panel;

        // 先判 APP_URL:它坏了,订阅另配域名也救不了邀请链接和邮件
        foreach ([$panel, $url] as $u) {
            if ($u === '' || str_contains($u, 'localhost') || str_contains($u, '127.0.0.1')) {
                $which = $u === $panel ? 'APP_URL' : 'SUB_URL_BASE';

                return $this->x('bad', '订阅地址',
                    "{$which} 现在是 {$u} —— 用户拿到的链接会指向这个地址，客户端打不开。"
                    .'改 .env 后 php artisan config:clear', null);
            }
            if (str_contains($u, 'trycloudflare.com')) {
                return $this->x('warn', '订阅地址',
                    '地址指向 trycloudflare 的临时域名 —— 它每次隧道重启都会变，'
                    .'变了之后所有人的订阅和所有节点会同时失效', null);
            }
        }
        // `[!!]` 订阅域名与面板域名【应当分开】。订阅 URL 是每个用户的客户端每天
        //   都要访问的东西 —— 同域时,面板域名一旦被封或被污染,用户不只是打不开
        //   网页,是【连订阅也拉不了】:换不了节点、加不了新设备。
        // `[!!]` 而订阅 URL 一旦发出去就【收不回来】(嵌在每个人的客户端配置里),
        //   所以这件事要在没有用户时定下来 —— 因此只报 warn 不报 bad:
        //   它不影响现在能不能用,只影响以后改起来贵不贵。
        // `[!!]` 2026-09-24:这一项的建议改过一次。原文写的是"去 .env 配 SUB_URL_BASE",
        //   但 SUB_URL_BASE 配好之后它照样 warn(sub.91app.shop 与 app.91app.shop
        //   仍是同一个可注册域),于是建议变成了一句【做完也不会消失的指令】——
        //   自检里最坏的一种文案:照着做了还红着,下次就没人信它了。
        //   现在分两种情形说话,并且说清代价。
        // `[!!]` 代价不对称,这是 2026-09-24 查 routes/api.php 才发现的:
        //   客户端要调 /auth/login /plans /order/*/pay 等等,【面板域名写死在
        //   每个已装的客户端二进制里】—— 搬面板要发新版+全员升级;
        //   搬订阅只要用户重新导入一次。所以真要分开时,搬的是订阅不是面板。
        // `[D]` 已决定接受本项 warn(见 docs/decisions/domain-allocation.md):
        //   面板与订阅同域,换来的是客户端与节点都不用动。
        $panelHost = parse_url($panel, PHP_URL_HOST) ?: '';
        $subHost = parse_url($url, PHP_URL_HOST) ?: '';
        if ($panelHost !== '' && $subHost !== '' && $this->sameRegistrable($panelHost, $subHost)) {
            $configured = $base !== '';

            return $this->x('warn', '订阅地址',
                "{$url} —— 与面板（{$panelHost}）同一个可注册域。"
                .'面板域名被封时订阅会一起失效：用户不只是打不开网页，是连订阅也拉不了，'
                .'换不了节点、加不了新设备。',
                $configured
                    // 已经配了独立的订阅地址,只是还在同一个注册域 —— 别再叫人去配 SUB_URL_BASE
                    ? '已是独立子域，但仍在同一注册域。要真正分开，把 SUB_URL_BASE 换成'
                      .'另一个注册域下的主机名（代价：用户重新导入一次订阅）。'
                      .'注意别反过来搬面板 —— 面板域名写死在已发出的客户端二进制里，那要发新版。'
                      .'已知并接受时见 docs/decisions/domain-allocation.md'
                    : '.env 里配 SUB_URL_BASE，指向另一个注册域下的主机名');
        }

        return $this->x('ok', '订阅地址', $url);
    }

    /** 两个主机名是否属于同一个可注册域（粗略取末两段，够用于"有没有分开"这个判断）。 */
    private function sameRegistrable(string $a, string $b): bool
    {
        $tail = function (string $h): string {
            $p = explode('.', mb_strtolower(trim($h, '.')));

            return implode('.', array_slice($p, -2));
        };

        return $tail($a) === $tail($b);
    }

    private function x(string $level, string $title, string $detail, ?string $fix = null): array
    {
        return compact('level', 'title', 'detail', 'fix');
    }
}
