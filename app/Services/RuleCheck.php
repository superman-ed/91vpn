<?php

namespace App\Services;

use App\Models\ForwardRule;

/**
 * 规则预演：保存前告诉运维「节点会不会认这条规则」。
 *
 * `[!!]` **这是面板侧的复刻，不是权威判定。** 权威在 agent 的
 * `relay.NodeRoutes.Validate()`（Go）。两处用两种语言写同一套规则，
 * 天然会漂 —— 本项目已经因此吃过亏（面板的出站下拉框里有 vless，
 * 而 agent 根本没实现）。
 *
 * 因此：
 *   - 只检查**已经咬过人的**那些条件，不追求覆盖全部校验；
 *   - 每条都指明后果的**范围**（整份被拒 / 这条被跳过 / 只是提醒）——
 *     "有问题"三个字没用，运维要知道它会不会连累别的规则；
 *   - 跨语言一致性由 sogacore 的 relaypanel_contract_test.go 兜底：
 *     面板真实下发的 JSON 会被喂给 Go 的校验器。
 *
 * 严重度：
 *   reject  节点会拒绝【整份】规则 —— 该节点上所有中转一起停
 *   skip    节点会跳过【这一条】，其余规则照常
 *   broken  规则会下发、节点也照常跑，但**这条链路上的用户连不上**
 *   warn    能跑，但多半不是想要的结果
 *
 * `[!!]` broken 是为「配对错开」这类故障单开的一档：它既不是 reject
 * （节点不会拒绝下发），也不是 warn（后果不是"多半不对"，是全断）。
 * 把它混进任何一档，运维读到的紧急程度都会错。
 */
class RuleCheck
{
    // `[!!]` 字符串插值里凡是变量后面紧跟全角标点，都要用 `{$var}` 界定。
    // PHP 会把 `"$where：PROXY"` 里的 `$where：PROXY` 整个当成变量名
    // （全角冒号被视作标识符字符），结果变量为空、句子残缺 ——
    // 而这只是一条 Warning，页面照常渲染，很容易漏过去。

    /** agent 的出站构建器实现了的类型（internal/core/xray/relay.go）。 */
    private const OUT_TYPES = ['direct', 'vmess', 'trojan', 'ss', 'socks', 'http'];

    private const IN_TYPES = ['direct', 'vmess', 'vless', 'trojan', 'socks'];

    /** @return array<int,array{level:string,text:string}> */
    public static function check(ForwardRule $rule): array
    {
        $p = [];
        $rule->loadMissing('outbounds');
        $opts = $rule->inbound_opts ?? [];
        $cred = $rule->inbound_cred ?? [];

        // ---- 入站 ----
        if (! in_array($rule->inbound_type, self::IN_TYPES, true)) {
            $p[] = self::x('reject', "入站类型 {$rule->inbound_type} 不受支持（"
                .implode('/', self::IN_TYPES).'）');
        }
        if (in_array($rule->inbound_type, ['vmess', 'vless'], true) && empty($cred['uuid'])) {
            $p[] = self::x('reject', "{$rule->inbound_type} 入站缺 credential.uuid —— "
                .'保存时应当自动铸造，没有说明出了别的问题');
        }
        if ($rule->inbound_type === 'trojan' && empty($cred['password'])) {
            $p[] = self::x('reject', 'trojan 入站缺 credential.password');
        }
        if ($rule->inbound_type === 'direct' && ! empty($cred)) {
            $p[] = self::x('reject', 'direct 入站不解协议，不该带凭据');
        }
        if ($rule->inbound_security === 'reality') {
            $r = $opts['reality'] ?? [];
            if (empty($r['dest']) || empty($r['server_names']) || empty($r['private_key'])) {
                $p[] = self::x('reject', '入站 reality 需要 dest / server_names / private_key —— '
                    .'密钥用编辑页的「生成密钥对」');
            }
        }
        if ($rule->inbound_transport === 'grpc' && empty($opts['grpc']['service_name'])) {
            $p[] = self::x('reject', 'grpc 传输需要 serviceName，否则建不了链');
        }

        // ---- 出站 ----
        $primary = $rule->outbounds->where('pool', 'primary')->where('enabled', true);
        if ($primary->isEmpty()) {
            $p[] = self::x('skip', '主池没有可用出站 —— 节点会整条跳过这条规则');
        }

        $serverNames = $opts['reality']['server_names'] ?? [];
        foreach ($rule->outbounds as $o) {
            $where = ($o->pool === 'backup' ? '备池' : '主池')."出站 {$o->out_type}";
            $oo = $o->out_opts ?? [];

            if (! in_array($o->out_type, self::OUT_TYPES, true)) {
                $p[] = self::x('reject', "{$where}：类型不受支持（agent 只实现了 "
                    .implode('/', self::OUT_TYPES).'）');
            }
            // 抗封约束 A
            if (! $o->trusted_transit && ! in_array($o->out_security, ['tls', 'reality'], true)) {
                $p[] = self::x('reject', "{$where}：未伪装（security="
                    .($o->out_security ?: 'none').'）又没勾「走专线」—— '
                    .'过墙跳裸奔会被 DPI 识别并封 IP');
            }
            if ($o->out_security === 'reality') {
                if (empty($oo['reality']['public_key'])) {
                    $p[] = self::x('reject', "{$where}：reality 出站需要对端公钥");
                }
                if (empty($o->sni)) {
                    $p[] = self::x('reject', "{$where}：reality 出站需要 SNI（须为对端 server_names 之一）");
                } elseif ($serverNames && ! in_array($o->sni, (array) $serverNames, true)) {
                    // `[!]` 只在本规则自己也是 reality 入站时才比对 ——
                    // 拨向别的节点时，对端的 server_names 面板这边不一定知道。
                    $p[] = self::x('warn', "{$where}：SNI「{$o->sni}」不在本规则的 server_names 里。"
                        .'若对端是另一条规则，请确认它的 server_names 包含这个值');
                }
                if (($o->out_transport ?: 'tcp') !== 'tcp') {
                    $p[] = self::x('reject', "{$where}：reality 出站只支持 tcp 传输");
                }
            }
            if ($o->out_type === 'direct' && ! empty($oo['mux']['enabled'])) {
                $p[] = self::x('reject', "{$where}：direct 出站不能开 mux（裸 TCP 之上没有可复用的协议层）");
            }
            if ((int) $o->send_proxy_protocol > 0 && $o->out_type !== 'direct') {
                $p[] = self::x('reject', "{$where}：PROXY protocol 只支持 direct 出站");
            }
            if ($o->out_transport === 'grpc' && empty($oo['grpc']['service_name'])) {
                $p[] = self::x('reject', "{$where}：grpc 传输需要 serviceName");
            }
            if ($o->source_in_source_out ?? false) {
                $p[] = self::x('reject', "{$where}：source_in_source_out 尚未实现（D-3 保留占位）");
            }
        }

        // ---- 配对：中转发 PROXY 头 ↔ 落地收 PROXY 头 ----
        $p = array_merge($p, self::proxyPairing($rule));

        // ---- 发 PROXY 头的裸端口转发：UDP 会被节点关掉 ----
        $p = array_merge($p, self::proxyProtocolKillsUdp($rule));

        // ---- 运行时才会显现的 ----
        if (! $rule->hc_enabled && $primary->count() > 1) {
            $p[] = self::x('warn', '多个出站却关了健康检查 —— 死掉的上游仍会被轮到');
        }
        foreach ((array) ($rule->inbound_node_set ?? []) as $nid) {
            $n = \App\Models\Node::find($nid);
            if ($n && ! $n->forwards()) {
                $p[] = self::x('reject', "节点「{$n->name}」的角色是 {$n->role}，"
                    .'拿不到转发规则（请求下发端点会得到 404）');
            }
        }
        if (empty($rule->inbound_node_set)) {
            $p[] = self::x('warn', '没有指定运行节点 —— 这条规则不会下发给任何人');
        }

        return $p;
    }

    /**
     * 「中转发 PROXY 头」与「落地收 PROXY 头」必须成对。
     *
     * `[!!]` 这条错配在**两端都不报错**：中转日志一切正常、落地一行都没有，
     * 只有客户端连不上（sogacore 的 compatibility/b2-reality-through-relay.md §2.5）。
     * 面板是唯一有机会在出事**之前**发现它的地方 —— 所以这里的默认必须是
     * 「未知不算通过」：查不到、没上报、上报过期、落地面板不可达，一律出 warn，
     * 绝不静默放行。
     *
     * 反向也要查：落地开了收头而这条出站不发头，同样是全断
     * （落地会拒绝每一个不带头的连接）。
     *
     * @return array<int,array{level:string,text:string}>
     */
    private static function proxyPairing(ForwardRule $rule): array
    {
        $svc = app(ForwardRuleService::class);
        $pairs = [];   // [ [出站, host, 发头版本], ... ]
        foreach ($rule->outbounds as $o) {
            if (! $o->enabled) {
                continue;
            }
            foreach ($svc->targetsFor($o) as $dial) {
                $host = self::hostOf($dial);
                if ($host !== '') {
                    $pairs[] = [$o, $host, (int) $o->send_proxy_protocol];
                }
            }
        }
        if ($pairs === []) {
            return [];
        }

        // [!] ADR-008 之后这是【本地一次查询】：落地就在同一个库里。
        // 合并之前要跨面板走内部只读 API（LandingPosture + 两侧 token +
        // 宿主网关地址），那套已随合并删除。
        // [!!] 只认【落地角色】：中转与落地可能是同一台机器（同一个 server 地址
        // 两条节点记录）。不过滤的话 keyBy('server') 会让中转那条覆盖落地那条，
        // 于是配对校验查的是中转自己的收头状态 —— 一个看起来正常的错误答案。
        $nodes = \App\Models\Node::whereIn('server', array_column($pairs, 1))
            ->whereIn('role', ['landing', 'both'])
            ->get()->keyBy('server');

        $p = [];
        $said = [];   // 同一个 (host,问题) 只说一次
        $once = function (string $k, string $level, string $text) use (&$p, &$said) {
            if (! isset($said[$k])) {
                $said[$k] = true;
                $p[] = self::x($level, $text);
            }
        };

        foreach ($pairs as [$o, $host, $send]) {
            $where = ($o->pool === 'backup' ? '备池' : '主池')."出站 → {$host}";
            $n = $nodes[$host] ?? null;

            if (! $n) {
                $once("miss:{$host}", 'warn', "{$where}：库里找不到这个地址的节点，无法校验 PROXY 头配对。"
                    .'地址写法（域名/IP）要与节点表里的一致');
                continue;
            }
            $st = $n->acceptProxyPosture();
            if (is_null($st['reported'])) {
                $once("unknown:{$host}", 'warn', "{$where}：落地"
                    .($st['reported_at'] ? '的上报已过期（最后一次 '.$st['reported_at']->diffForHumans().'）'
                        : '从未上报过 accept_proxy')
                    .' —— 按未知处理，不当作配对正常');
                continue;
            }

            if ($send > 0 && $st['reported'] === false) {
                $once("nohdr:{$host}", 'broken', "{$where}：这条出站发 PROXY 头（v{$send}），"
                    .'而落地**没有**在收 —— 这条链路上的用户会全断，且中转与落地两侧都不会报错');
            }
            if ($send === 0 && $st['reported'] === true) {
                $once("needhdr:{$host}", 'broken', "{$where}：落地开着 PROXY 头收取（那个端口的"
                    .'**每个**连接都必须带头），而这条出站不发 —— 同样全断');
            }
            if ($st['expected'] !== $st['reported']) {
                $once("drift:{$host}", 'warn', "{$where}：面板上配的是 "
                    .($st['expected'] ? '收头' : '不收头').'，而节点实际在跑 '
                    .($st['reported'] ? '收头' : '不收头')
                    .' —— 节点还没拉到新配置，或回落了本地 agent.conf 的值');
            }
        }

        return $p;
    }

    /** 从 host:port 取 host（兼容 [::1]:443 这种写法）。 */
    private static function hostOf(string $dial): string
    {
        if (str_starts_with($dial, '[')) {
            $end = strpos($dial, ']');

            return $end === false ? '' : substr($dial, 1, $end - 1);
        }
        $pos = strrpos($dial, ':');

        return $pos === false ? $dial : substr($dial, 0, $pos);
    }

    /**
     * `[!!]` 裸端口转发（direct 入站）一旦有出站发 PROXY 头，节点会把这条规则的
     * **UDP 关掉**。这里要在保存时就说出来，否则运维只会在"某个 UDP 服务不通"
     * 时才发现，而那时他会先去查应用、网络和防火墙。
     *
     * 为什么节点要关：[D] 实测（sogacore `lab/udp-through-relay-probe.sh`）
     * freedom 出站的 proxyProtocol 对 UDP 也生效，而且它把 PROXY 头当成
     * **一个独立的数据报**先发出去，载荷在下一个包里 —— 接收端的整条 UDP 流
     * **错位一个包**。而落地的 acceptProxyProtocol 是个 **TCP sockopt**，
     * UDP 路径上没有它，所以两端"正确配对"也剥不掉。
     *
     * 关掉是取舍：UDP 不通是能被发现的故障，静默错位不是。
     *
     * `[!]` 级别用 warn 不用 broken：这条规则如果本来只跑 TCP（vless 落地挂在
     * 中转后面就是这种），关掉 UDP 没有任何影响。面板判不出运维的意图，
     * 所以只陈述事实与代价，不替他判定"坏了"。
     *
     * @return array<int,array{level:string,text:string}>
     */
    private static function proxyProtocolKillsUdp(ForwardRule $rule): array
    {
        if ($rule->inbound_type !== 'direct') {
            return [];   // 解协议的入站（vmess/vless/…）里 UDP 走 XUDP，封在 TCP 连接内，不受影响
        }

        foreach ($rule->outbounds as $o) {
            if ($o->enabled && (int) $o->send_proxy_protocol > 0) {
                return [self::x('warn', '这条是裸端口转发，且出站发 PROXY 头（v'
                    .(int) $o->send_proxy_protocol.'）—— 节点会把本规则的 **UDP 关掉**。'
                    .'PROXY 头在 UDP 上会单独占一个数据报，使接收端的整条流错位一个包，'
                    .'而落地的「收 PROXY 头」是 TCP 选项、剥不掉。'
                    .'要承载 UDP 服务就关掉 send_proxy_protocol（代价是落地看不到真实客户端 IP）')];
            }
        }

        return [];
    }

    /** 只要有一条 reject，这条规则就会让【整个节点】的中转停摆。 */
    public static function willReject(array $problems): bool
    {
        foreach ($problems as $x) {
            if ($x['level'] === 'reject') {
                return true;
            }
        }

        return false;
    }

    private static function x(string $level, string $text): array
    {
        return ['level' => $level, 'text' => $text];
    }
}
