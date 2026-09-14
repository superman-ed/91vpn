<?php

namespace App\Services;

use App\Models\Node;
use App\Models\Plan;

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
     *   level: ok | warn | bad
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
        ];
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
        $hb = \Illuminate\Support\Facades\Cache::get(
            \App\Console\Commands\RecordBackup::KEY, []
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
        $when = \Illuminate\Support\Carbon::createFromTimestamp($lastOk)->format('m-d H:i');

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
        $tasks = \App\Providers\AppServiceProvider::WATCHED_TASKS;
        $never = $stale = [];
        foreach ($tasks as $sig) {
            $hb = \Illuminate\Support\Facades\Cache::get("task_hb:{$sig}");
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
    private function mail(): array
    {
        $host = (string) setting('smtp_host', '');
        $user = (string) setting('smtp_username', '');

        return $host !== '' && $user !== ''
            ? $this->x('ok', '邮件', "已配（{$host}）")
            : $this->x('bad', '邮件', '没配 SMTP —— 用户注册收不到验证码，会卡在注册页，'
                .'而你这边看不到任何异常（他根本没注册成功）', '/admin/settings');
    }

    private function payment(): array
    {
        return (string) setting('epay_pid', '') !== '' && (string) setting('epay_url', '') !== ''
            ? $this->x('ok', '支付', '已配')
            : $this->x('warn', '支付', '没配支付 —— 用户能注册能用免费节点，但买不了套餐',
                '/admin/settings');
    }

    /** [!] 订阅链接由 APP_URL 派生。填成 localhost 的话，发出去的订阅客户端打不开。 */
    private function subUrl(): array
    {
        $url = (string) config('app.url');
        if ($url === '' || str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            return $this->x('bad', '订阅地址',
                "APP_URL 现在是 {$url} —— 用户拿到的订阅链接会指向这个地址，客户端打不开。"
                .'改 .env 的 APP_URL 为用户面的公网地址，然后 php artisan config:clear', null);
        }
        if (str_contains($url, 'trycloudflare.com')) {
            return $this->x('warn', '订阅地址',
                'APP_URL 指向 trycloudflare 的临时域名 —— 它每次隧道重启都会变，'
                .'变了之后所有人的订阅和所有节点会同时失效', null);
        }

        return $this->x('ok', '订阅地址', $url);
    }

    private function x(string $level, string $title, string $detail, ?string $fix = null): array
    {
        return compact('level', 'title', 'detail', 'fix');
    }
}
