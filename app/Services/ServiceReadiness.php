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
        ];
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
