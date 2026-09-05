<?php

namespace App\Services;

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;

/**
 * 把转发规则从「舰队视角」编译成「单节点视角」。
 *
 * 两者是同一信息的两个层级（sogacore docs/RELAY-SCHEMA.md §0）：
 *
 *   舰队视角（本表）      规则挂在一组节点上、出站可以引用另一条规则
 *          ↓ 编译
 *   单节点视角（下发）    我监听 X、拨号到 Y —— 具体、无引用
 *
 * [!!] 编译的要害是**把引用解析干净**。agent 不认识"节点集/分组/引用另一条
 *      规则"这些概念；下发里残留任何引用类字段，agent 要么报错要么误读。
 *      所以本服务的输出里只能出现具体地址。
 */
class ForwardRuleService
{
    /**
     * 编译出某个节点该拿到的规则。
     *
     * @return array{rules: array} 形如 {"rules":[...]}，即下发 JSON 的 data 段
     */
    public function compileForNode(Node $node): array
    {
        $rules = ForwardRule::with('outbounds')
            ->where('enabled', true)
            ->get()
            // 只要入站跑在本节点上的规则。
            //
            // [!] 在 PHP 侧过滤而不是 SQL：inbound_node_set 是 JSON 列，
            // 不同 MySQL 版本的 JSON 查询语法有出入，而规则总数很少
            // （一个部署通常几十条），全取回来过滤代价可忽略、行为可预期。
            ->filter(fn (ForwardRule $r) => $r->runsOn($node->id));

        $out = [];
        foreach ($rules as $rule) {
            $compiled = $this->compileRule($rule, $node);
            if ($compiled !== null) {
                $out[] = $compiled;
            }
        }

        return ['rules' => $out];
    }

    /**
     * 编译单条规则。返回 null 表示这条规则对本节点无效（应跳过而不是下发半份）。
     */
    private function compileRule(ForwardRule $rule, Node $node): ?array
    {
        $primary = $this->compilePool($rule, 'primary');
        // 一条没有可用出站的规则不该下发：agent 会因为"至少需要一个 outbound"
        // 而拒绝【整份】配置，一条坏规则会连累其余全部规则。
        if ($primary === []) {
            return null;
        }

        return [
            'id' => (int) $rule->id,
            'name' => (string) $rule->name,
            'enabled' => true,
            'speed_limit_mbps' => (float) $rule->speed_limit,
            'inbound' => $this->compileInbound($rule, $node),
            'balance' => $rule->balance,
            'backup_balance' => $rule->backup_balance,
            'health_check' => [
                'enabled' => (bool) $rule->hc_enabled,
                'interval_sec' => (int) $rule->hc_interval_sec,
                'max_fail' => (int) $rule->hc_max_fail,
                'max_success' => (int) $rule->hc_max_success,
            ],
            'outbounds' => $primary,
            'backup_outbounds' => $this->compilePool($rule, 'backup'),
        ];
    }

    private function compileInbound(ForwardRule $rule, Node $node): array
    {
        $in = [
            'type' => $rule->inbound_type,
            'listen_ip' => $rule->listen_all_nics ? '' : (string) ($rule->listen_nic_ip ?? ''),
            'port' => (string) $rule->listen_port,
            'mptcp' => (bool) $rule->mptcp,
            'transport' => (string) ($rule->inbound_transport ?? ''),
            'security' => (string) ($rule->inbound_security ?? ''),
            'accept_proxy_protocol' => (bool) $rule->accept_proxy_protocol,
            // [decided] D-2：这是面板铸造的【节点间凭据】，不是用户凭据 ——
            // 中转不认证用户（D-1）。
            'credential' => $rule->inbound_cred ?? new \stdClass(),
        ];

        // 传输/安全层的附加块，只在用到时才带上 —— agent 侧对多余的块不宽容
        // （比如 security≠reality 却给了 reality 块，属于配置矛盾）。
        //
        // [!] 这些【不能】塞进 credential：那一列在 agent 侧是凭据
        // （uuid/password/cipher/username），多出来的键要么被忽略、要么被当成
        // 配置错误。而且语义上也不对 —— REALITY 的 dest / server_names 不是秘密，
        // 和用户凭据混在一起会让"凭据轮换"顺手把传输参数也换掉。
        $opts = $rule->inbound_opts ?? [];
        foreach (['ws', 'grpc', 'reality'] as $k) {
            if (isset($opts[$k])) {
                $in[$k] = $opts[$k];
            }
        }

        return $in;
    }

    /** @return array<int,array> */
    private function compilePool(ForwardRule $rule, string $pool): array
    {
        $out = [];
        foreach ($rule->outbounds as $ob) {
            if ($ob->pool !== $pool || ! $ob->enabled) {
                continue;
            }
            foreach ($this->compileOutbound($ob) as $one) {
                $out[] = $one;
            }
        }

        return $out;
    }

    /**
     * 编译一个出站条目。
     *
     * [!] 一个条目可能展开成【多个】具体出站：target_node_set 指定一组落地时，
     * 每个落地都是一个独立的出站（这正是"落地·2个均衡"的来源）。
     *
     * @return array<int,array>
     */
    private function compileOutbound(ForwardOutbound $ob): array
    {
        // out_type=relay_rule 是「引用另一条规则」——多跳的组合原语。
        //
        // [!!] 引用必须在此解析成【被引用规则的入站地址】。解析不了就跳过
        // 这个出站，而不是把引用原样下发 —— agent 不认识 relay_rule，
        // 原样下发会让它整份配置校验失败。
        if ($ob->out_type === 'relay_rule') {
            return $this->resolveRuleRef($ob);
        }

        $targets = $this->resolveTargets($ob);
        $out = [];
        foreach ($targets as $dial) {
            $out[] = [
                'type' => $ob->out_type,
                'dial' => $dial,
                'transport' => (string) ($ob->out_transport ?? ''),
                'security' => (string) ($ob->out_security ?? ''),
                'fingerprint' => (string) ($ob->fingerprint ?? ''),
                'mptcp' => (bool) $ob->out_mptcp,
                'sni' => (string) ($ob->sni ?? ''),
                'send_proxy_protocol' => (int) $ob->send_proxy_protocol,
                'source_in_source_out' => (bool) $ob->source_in_source_out,
                // [!!] 抗封约束 A：过墙那跳必须伪装。agent 会拒绝
                // 「未伪装且未声明 trusted_transit」的出站。
                'trusted_transit' => (bool) $ob->trusted_transit,
                'weight' => (int) $ob->weight,
                'credential' => $ob->out_cred ?? new \stdClass(),
            ];
        }

        return $out;
    }

    /**
     * 把 relay_rule 引用解析成被引用规则的入站地址。
     *
     * 被引用规则的入站可能跑在多个节点上 —— 每个都是一个可选的下一跳。
     *
     * @return array<int,array>
     */
    private function resolveRuleRef(ForwardOutbound $ob): array
    {
        $ref = ForwardRule::find($ob->relay_rule_ref);
        if ($ref === null || ! $ref->enabled) {
            return [];
        }
        $nodes = Node::whereIn('id', $ref->inbound_node_set ?? [])
            ->where('enabled', true)->get();

        $out = [];
        foreach ($nodes as $n) {
            $out[] = [
                // 被引用规则的入站协议就是这一跳的出站协议。
                'type' => $this->inboundTypeToOutType($ref->inbound_type),
                'dial' => $n->server . ':' . $this->firstPort($ref->listen_port),
                'transport' => (string) ($ref->inbound_transport ?? ''),
                'security' => (string) ($ref->inbound_security ?? ''),
                'fingerprint' => (string) ($ob->fingerprint ?? ''),
                'mptcp' => (bool) $ob->out_mptcp,
                'sni' => (string) ($ob->sni ?? ''),
                'send_proxy_protocol' => (int) $ob->send_proxy_protocol,
                'source_in_source_out' => false,
                'trusted_transit' => (bool) $ob->trusted_transit,
                'weight' => (int) $ob->weight,
                // [decided] D-2：跨节点凭据 = 被引用规则的入站凭据，编译期注入。
                'credential' => $ref->inbound_cred ?? new \stdClass(),
            ];
        }

        return $out;
    }

    /**
     * 解析具体出站的拨号目标。
     *
     * target_node_set 优先于 target_addr：前者是"发给这几个落地节点"，
     * 后者是"发给这个地址"。两者都给时以节点集为准（更具体）。
     *
     * @return array<int,string>
     */
    private function resolveTargets(ForwardOutbound $ob): array
    {
        $port = $this->firstPort((string) ($ob->target_port ?? ''));

        if (is_array($ob->target_node_set) && $ob->target_node_set !== []) {
            return Node::whereIn('id', $ob->target_node_set)
                ->where('enabled', true)->get()
                ->map(fn (Node $n) => $n->server . ':' . ($port !== '' ? $port : $n->port))
                ->all();
        }
        if ($ob->target_addr) {
            return [$ob->target_addr . ':' . $port];
        }

        return [];
    }

    /**
     * 端口段取第一个 —— 出站只能拨一个端口。
     *
     * [!] 端口段（"10100-10110"）只对【入站】有意义（监听一段）；
     * 出站给一个段是配置错误，取第一个是明确的降级而不是猜。
     */
    private function firstPort(string $spec): string
    {
        $spec = trim($spec);
        if ($spec === '') {
            return '';
        }
        $dash = strpos($spec, '-');

        return $dash === false ? $spec : trim(substr($spec, 0, $dash));
    }

    /** 被引用规则的入站协议 → 引用方的出站协议。 */
    private function inboundTypeToOutType(string $inboundType): string
    {
        // 入站与出站的枚举大体同名；direct 入站对应 direct 出站。
        return $inboundType;
    }
}
