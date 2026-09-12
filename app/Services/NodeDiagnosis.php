<?php

namespace App\Services;

use App\Models\ForwardRule;
use App\Models\Node;

/**
 * 节点一键诊断：把散在四个页面的线索一次跑完，直接给结论。
 *
 * [!!] 它的价值不在"跑了什么检查"，而在**把判断说出来**。
 * 现在这些线索确实都能查到，但分别在节点列表、规则列表、订阅、节点机的
 * journalctl 里 —— 而故障的表现往往是"某一项绿着，另一项才是真因"。
 * 最典型的：端口没放行时，心跳正常、/ready 正常、面板显示在线，
 * 只有客户端连不上（心跳是节点【往外发】的，不需要入站放行）。
 *
 * [!] 每一项只报**这一项自己能证明的东西**，不替别的项下结论。
 * 拿不准就说"查不了"而不是猜 —— 一个错误的绿灯比没有灯更坏。
 */
class NodeDiagnosis
{
    /** 端口探测的超时。[!] 要短：诊断是同步请求，卡住的话页面就转圈。 */
    private const DIAL_TIMEOUT = 3;

    /**
     * @return array<int,array{level:string,title:string,detail:string}>
     *   level: ok | warn | bad | unknown
     */
    public function run(Node $node): array
    {
        return array_values(array_filter(array_merge(
            [$this->heartbeat($node)],
            [$this->port($node)],
            [$this->dest($node)],
            [$this->pairing($node)],
            [$this->visibility($node)],
        )));
    }

    /** 心跳：节点还在不在和面板说话。 */
    private function heartbeat(Node $node): array
    {
        if (! $node->last_heartbeat) {
            return $this->x('bad', '心跳', '从来没有收到过心跳 —— 节点上的 agent 没装起来，'
                .'或者 webapi_url / node_id / secret 配错了');
        }
        $age = time() - (int) $node->last_heartbeat;
        if ($age > 180) {
            return $this->x('bad', '心跳', "最后一次是 {$age} 秒前 —— 节点失联了。"
                .'去节点上看 `systemctl is-active agent` 与 `journalctl -u agent -n 50`');
        }

        return $this->x('ok', '心跳', "{$age} 秒前，正常");
    }

    /**
     * 端口：从面板这台机器能不能连上节点的入站端口。
     *
     * [!!] 这一项最值得单独跑。端口没在防火墙放行时，心跳、/ready、
     * 面板在线状态【全是绿的】—— 因为心跳是节点往外发的，不需要入站放行。
     * 人会去查客户端、查订阅、查协议，而问题在一个谁都没看的地方。
     *
     * [!] 探测源是**面板这台机器**，不是用户的网络。这里通不代表用户那边通
     * （中间还可能有运营商封锁），但这里不通就一定有问题。结论里写清楚。
     */
    private function port(Node $node): array
    {
        if ($node->forwards()) {
            // 中转的监听端口来自转发规则，不在节点上；改探它的规则端口。
            return $this->relayPorts($node);
        }
        if ((int) $node->port <= 0) {
            return $this->x('warn', '端口', '这台落地没有端口 —— 中转拨不过去，去节点页补上');
        }

        [$open, $err] = $this->dial($node->server, (int) $node->port);

        // [!!] 开了 PROXY 头收取的落地，【连不上才是对的】——
        // 那个端口应当只对中转可达（PROXY 头无认证，谁能连上谁就能伪造来源 IP）。
        // 不分这一层的话，一台配置完全正确的落地会被报成"有问题"，
        // 而一台真的裸奔的落地会被报成"正常" —— 两个结论都反了。
        if ($node->accept_proxy_protocol) {
            return $open
                ? $this->x('warn', '端口', "{$node->server}:{$node->port} 从面板【能连上】—— "
                    .'本节点开了 PROXY 头收取，这个端口应当只对中转可达。'
                    .'PROXY 头没有认证，能连上的人可以随意伪造来源 IP、污染在线 IP 与审计。'
                    .'去节点机上把这个端口限制为只允许中转的地址')
                : $this->x('ok', '端口', "{$node->server}:{$node->port} 从面板连不上 —— "
                    .'这是【对的】：本节点开了 PROXY 头收取，该端口本就应当只对中转可达。'
                    .'用户是经由中转连进来的，见下方"用户可见性"');
        }

        if ($open) {
            return $this->x('ok', '端口', "{$node->server}:{$node->port} 可连");
        }

        return $this->x('bad', '端口', "{$node->server}:{$node->port} 连不上（{$err}）。"
            .'查节点机的 `ufw status`，以及云厂商控制台的安全组 —— '
            .'这一项不通时心跳仍然正常，因为心跳是节点往外发的');
    }

    /** 中转：逐条规则探它的监听端口。 */
    private function relayPorts(Node $node): array
    {
        $checked = [];
        foreach (ForwardRule::where('enabled', true)->get() as $rule) {
            if (! in_array($node->id, (array) ($rule->inbound_node_set ?? []), false)) {
                continue;
            }
            $port = $this->firstPort((string) $rule->listen_port);
            if ($port === null) {
                continue;
            }
            [$open, $err] = $this->dial($node->server, $port);
            $checked[] = ($open ? '✓' : '✗')." {$rule->name} :{$port}".($open ? '' : "（{$err}）");
        }
        if ($checked === []) {
            return $this->x('warn', '端口', '这台中转上没有启用中的转发规则 —— 它现在什么都不监听');
        }
        $bad = count(array_filter($checked, fn ($c) => str_starts_with($c, '✗')));

        return $this->x($bad ? 'bad' : 'ok', '端口', implode('；', $checked)
            .($bad ? '。连不上的那些：查中转机的防火墙与安全组' : ''));
    }

    /** REALITY 的 dest：端口在听不等于能握手。 */
    private function dest(Node $node): ?array
    {
        if (! $node->usesReality()) {
            return null;
        }
        return match ($node->destHealth()) {
            'ok' => $this->x('ok', 'REALITY dest', "{$node->reported_dest} 可达"),
            'down' => $this->x('bad', 'REALITY dest',
                "{$node->reported_dest} 连续失败 {$node->reported_dest_failures} 次 —— "
                .'【新连接全部失败，包括密钥正确的老用户】。REALITY 服务端在读 ClientHello '
                .'之前就要先连上 dest（它全程参与握手），连不上就直接断，握手根本没机会开始。'
                .'已建立的连接不受影响，所以现象是"老连接好好的、新连接全断、'
                .'端口还在听、面板还显示在线"。换一个 dest'),
            default => $this->x('unknown', 'REALITY dest',
                '节点没报过 dest 探活，或上报已过期 —— 等一个心跳周期再看'),
        };
    }

    /** PROXY 头配对：面板配的 vs 节点实际在跑的。 */
    private function pairing(Node $node): ?array
    {
        $st = $node->acceptProxyPosture();
        if ($st['reported'] === null) {
            return $st['expected']
                ? $this->x('unknown', 'PROXY 头', '面板配了收头，但节点没报过它实际在跑什么 —— '
                    .'等一个心跳周期；一直如此说明 agent 版本太旧')
                : null;
        }
        if ($st['expected'] === $st['reported']) {
            return $this->x('ok', 'PROXY 头',
                $st['reported'] ? '面板配了收头，节点确实在收' : '两边都没开，一致');
        }

        return $this->x('bad', 'PROXY 头',
            $st['expected']
                ? '面板配了收头，而节点【实际没在收】 —— 中转发来的连接会全断，'
                    .'且两侧都不报错。节点可能还没拉到新配置，或内核没重启'
                : '面板没配收头，而节点【实际在收】 —— 直连客户端会被全部拒绝');
    }

    /** 用户能不能看到它、经由哪条路。 */
    private function visibility(Node $node): array
    {
        if ($node->forwards()) {
            return $this->x('ok', '用户可见性', '中转不进订阅（D-1）—— 用户连的是它的转发规则端口');
        }
        if (! $node->enabled) {
            return $this->x('warn', '用户可见性', '已禁用 —— 不进任何人的订阅（节点本身照常运行）');
        }
        if (! $node->online) {
            return $this->x('warn', '用户可见性', '面板认为它离线 —— 离线的节点不进订阅');
        }

        $eps = app(SubscriptionService::class)->entrypoints($node);
        if ($eps === []) {
            return $this->x('bad', '用户可见性', '订阅里【一条入口都没有】 —— '
                .($node->accept_proxy_protocol
                    ? '本节点开了 PROXY 头收取，所以不发直连条目，而又没有中转指向它'
                    : '检查端口与角色'));
        }
        $lines = array_map(
            fn ($e) => ($e['label'] === '' ? '直连' : $e['label'])." → {$e['server']}:{$e['port']}",
            $eps);

        return $this->x('ok', '用户可见性', '订阅里有 '.count($eps).' 条入口：'.implode('；', $lines)
            ."（等级门槛 {$node->node_class}，用户等级要 ≥ 它）");
    }

    /** @return array{0:bool,1:string} */
    private function dial(string $host, int $port): array
    {
        // [!] set_error_handler 而不是只靠 @:连接失败时 fsockopen 会发 E_WARNING,
        // 而 @ 在某些 error_reporting 配置下仍会把它打进输出 —— 实测在 tinker 里
        // 那行警告混进了诊断结果。诊断是要给人读的，混着 PHP 警告就毁了。
        set_error_handler(fn () => true);
        $fp = fsockopen($host, $port, $errno, $errstr, self::DIAL_TIMEOUT);
        restore_error_handler();
        if ($fp) {
            fclose($fp);

            return [true, ''];
        }

        return [false, $errstr ?: "errno {$errno}"];
    }

    private function firstPort(string $spec): ?int
    {
        $first = trim(explode('-', trim(explode(',', $spec)[0]))[0]);

        return ctype_digit($first) && (int) $first > 0 ? (int) $first : null;
    }

    private function x(string $level, string $title, string $detail): array
    {
        return ['level' => $level, 'title' => $title, 'detail' => $detail];
    }
}
