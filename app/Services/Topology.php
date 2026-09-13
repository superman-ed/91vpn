<?php

namespace App\Services;

use App\Models\ForwardRule;
use App\Models\Node;

/**
 * 中转拓扑：按【路径】看，不是按节点看。
 *
 * `[!!]` 这一页要回答的是「现在坏了的话，是哪一段」，
 * 不是「我们有几台机器」—— 后者节点列表已经答了。
 *
 * `[!]` 刻意【不画连线图】。三五个节点的力导向图是装饰：它看着像拓扑，
 * 却答不出"哪一段断了"「用户实际拿得到哪几条路」这两个真问题。
 * 按路径缩进列出来，配上分层健康态，信息密度和可读性都更高。
 *
 * 一条路径的形状：
 *
 *     客户端 ──▶ 中转 :监听端口 ──▶ 落地 :端口 ──▶ dest
 *                  或直连 ──▶ 落地
 *
 * 每一跳的健康由【有资格测的那一方】提供（见 LayerHealth）：
 *     中转活着       面板测（心跳）
 *     中转→落地通    只有中转测得了
 *     dest 可达      只有落地测得了
 */
class Topology
{
    public function __construct(
        private LayerHealth $layers,
        private SubscriptionService $subs,
    ) {}

    /**
     * @return array{landings:array,orphans:array,stats:array}
     */
    public function build(): array
    {
        $nodes = Node::with('outboundStatuses.rule')->orderBy('sort')->orderBy('id')->get();
        $rules = ForwardRule::with('outbounds')->get();

        $landings = [];
        $usedRelays = [];

        foreach ($nodes->whereIn('role', ['landing', 'both']) as $landing) {
            // `[!!]` 路径从【配置】枚举，不从订阅派生。
            // 订阅会把"已知不通"的那一跳摘掉（那对用户是对的），
            // 但对看拓扑的人是最坏的：他要看的正是"本该有、现在不通"的那一条，
            // 而它在订阅里已经消失了。所以这里列全部配置出来的路径，
            // 再逐条标注它在不在订阅里、为什么不在。
            // `[!!]` 两个判定要【都过】才算"用户拿得到"：
            //   entrypoints()  这个落地【怎么】到得了（路径层）
            //   accessibleNodes 这个落地【会不会被下发】（enabled/online/等级）
            // 只看前者会得出"停用的落地也在订阅里"——它确实有路径，
            // 但压根不会出现在用户的节点列表里。
            $offered = $landing->enabled && $landing->online;
            $inSub = [];
            if ($offered) {
                foreach ($this->subs->entrypoints($landing) as $e) {
                    $inSub[$e['server'].':'.$e['port']] = true;
                }
            }

            $paths = [];
            if ((int) $landing->port > 0 && $landing->accept_proxy_protocol !== true) {
                $key = $landing->server.':'.$landing->port;
                $paths[] = [
                    'kind' => 'direct', 'relay' => null,
                    'server' => $landing->server, 'port' => (int) $landing->port,
                    'hop' => null,
                    'in_sub' => isset($inSub[$key]),
                    'why_not' => isset($inSub[$key]) ? null
                        : ($offered ? '订阅未合成这条直连入口' : '落地'.($landing->enabled ? '已失联' : '已停用')),
                ];
            }
            foreach ($rules as $rule) {
                if (! $rule->enabled) {
                    continue;
                }
                $hop = $this->hopFor2($rule, $landing);
                if ($hop === null) {
                    continue;      // 这条规则不指向本落地
                }
                $port = $this->firstPort((string) $rule->listen_port);
                foreach ((array) ($rule->inbound_node_set ?? []) as $rid) {
                    $relay = $nodes->firstWhere('id', (int) $rid);
                    if (! $relay) {
                        continue;
                    }
                    $usedRelays[$relay->id] = true;
                    $key = $relay->server.':'.$port;
                    $state = $relay->relayHopHealth($rule->id);
                    $rows = $relay->outboundStatuses->where('rule_id', $rule->id)
                        ->reject->stale()->filter->measured();
                    $paths[] = [
                        'kind' => 'relay', 'relay' => $relay,
                        'server' => $relay->server, 'port' => $port,
                        'hop' => ['rule' => $rule, 'state' => $state,
                            'delay_ms' => (int) $rows->max('delay_ms'), 'proxy' => $hop],
                        'in_sub' => isset($inSub[$key]),
                        'why_not' => isset($inSub[$key]) ? null
                            : ($offered ? $this->whyNotInSub($relay, $state)
                                : '落地'.($landing->enabled ? '已失联' : '已停用').' —— 整个落地都不会下发'),
                    ];
                }
            }

            $l = $this->layers->forNode($landing);
            $landings[] = [
                'node' => $landing,
                'layers' => $l,
                'paths' => $paths,
                // `[!!]` 没有任何【在订阅里】的路径 = 用户根本拿不到这个落地。
                // 这是最值得一眼看到的状态，而节点列表上它照样显示"在线"。
                // 注意判据是 in_sub 而不是 paths 为空 —— 配置了一堆全都不通，
                // 与压根没配，对用户是同一件事。
                'unreachable' => ! collect($paths)->contains('in_sub', true),
                'visible' => $offered,
            ];
        }

        // 没承载任何路径的中转：白开着。可能是规则没绑它，也可能规则解析不出目标。
        $orphans = [];
        foreach ($nodes->whereIn('role', ['relay', 'both']) as $relay) {
            if (isset($usedRelays[$relay->id])) {
                continue;
            }
            $orphans[] = [
                'node' => $relay,
                'layers' => $this->layers->forNode($relay),
                'why' => $this->whyOrphan($relay, $rules),
            ];
        }

        return [
            'landings' => $landings,
            'orphans' => $orphans,
            'stats' => [
                'landings' => count($landings),
                'paths' => array_sum(array_map(fn ($l) => count($l['paths']), $landings)),
                'unreachable' => count(array_filter($landings, fn ($l) => $l['unreachable'])),
                'orphans' => count($orphans),
            ],
        ];
    }

    /** 这条规则有没有指向该落地；有则返回它发的 PROXY 头版本。 */
    private function hopFor2(ForwardRule $rule, Node $landing): ?int
    {
        foreach ($rule->outbounds as $ob) {
            if (! $ob->enabled) {
                continue;
            }
            $hits = in_array($landing->id, (array) ($ob->target_node_set ?? []), false)
                || ($ob->target_addr === $landing->server
                    && (string) $ob->target_port === (string) $landing->port);
            if ($hits) {
                return (int) $ob->send_proxy_protocol;
            }
        }

        return null;
    }

    /**
     * 配置里有这条路，订阅里却没有 —— 为什么。
     *
     * `[!]` 这正是这一页存在的理由之一：订阅摘掉一条入口是【静默】的，
     * 用户只是少了个选择，没人会发现。这里把原因说出来。
     */
    private function whyNotInSub(Node $relay, string $hopState): string
    {
        if (! $relay->enabled) {
            return '中转已停用';
        }
        if (! $relay->online) {
            return '中转心跳失联';
        }
        if ($hopState === 'down') {
            return '中转上报「到落地不通」—— 订阅已自动摘掉这条入口';
        }

        return '订阅未合成这条入口（检查规则监听端口是否有效）';
    }

    private function firstPort(string $spec): int
    {
        $first = trim(explode(',', $spec)[0] ?? '');

        return (int) (explode('-', $first)[0] ?? 0);
    }

    /** 中转为什么没承载路径 —— 说清楚，别只标一个"闲置"。 */
    private function whyOrphan(Node $relay, $rules): string
    {
        if (! $relay->enabled) {
            return '节点已停用';
        }
        if (! $relay->online) {
            return '心跳失联 —— 失联的中转不会被合成进订阅';
        }
        $bound = $rules->filter(fn ($r) => in_array($relay->id, (array) ($r->inbound_node_set ?? []), false));
        if ($bound->isEmpty()) {
            return '没有任何转发规则把它当入站节点';
        }
        if ($bound->where('enabled', true)->isEmpty()) {
            return '绑定的规则都停用了';
        }

        return '规则绑了它，但那些规则解析不出可达的落地 —— 去规则页看「解析不出任何拨号目标」';
    }
}
