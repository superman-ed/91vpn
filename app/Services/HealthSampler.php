<?php

namespace App\Services;

use App\Models\Node;
use App\Models\NodeHealthSpell;
use Illuminate\Support\Carbon;

/**
 * 采一次样：把每个节点当前是否可用，记进它的存活区段。
 *
 * `[!!]` 只【观察】，不制造故障。为了统计去杀中转，测出来的是
 * "人工注入故障后的表现"，不是"生产环境中的自然存活时间"——
 * 而后者才是我们要回答的问题。
 *
 * `[!!]`「可用」的定义与【订阅真的会不会把它发给用户】对齐：
 *     enabled && online && 分层健康态里没有一层是 bad
 * 不这么对齐的话，量的就是另一件事 —— 比如一个 dest 挂掉的落地，
 * 端口照常监听、心跳照常，但没有任何客户端能完成握手。
 * 把它算成"存活"，统计出来的数字和用户的体验就没有关系了。
 */
class HealthSampler
{
    /** 连续几次采样判失败才算区段结束。一次网络抖动不该被记成一次"死亡"。 */
    public const MISSES_TO_CLOSE = 2;

    /** 端口探测超时。只在【检出失效时】各探一次，不是每轮都探。 */
    public const PROBE_TIMEOUT = 3;

    /**
     * @param  (\Closure(string,int):bool)|null  $prober  探一个 host:port 通不通。
     *
     * `[!!]` 做成可注入的，是因为它是本服务里【唯一一处真实网络 I/O】。
     * 不注入的话测试会去连 203.0.113.x（TEST-NET，不可路由），
     * 每次卡满 3 秒超时 —— 套件又慢又不稳，而慢和不稳最后都会变成"没人跑测试"。
     */
    public function __construct(
        private LayerHealth $layers,
        private ?\Closure $prober = null,
    ) {}

    private function probe(string $host, int $port): bool
    {
        if ($this->prober) {
            return ($this->prober)($host, $port);
        }
        $fp = @fsockopen($host, $port, $e, $s, self::PROBE_TIMEOUT);
        if ($fp === false) {
            return false;
        }
        fclose($fp);

        return true;
    }

    /** @return array{opened:int,closed:int,confirmed:int} */
    public function sample(?Carbon $now = null): array
    {
        $now ??= now();
        $opened = $closed = $confirmed = 0;

        $open = NodeHealthSpell::whereNull('ended_at')->get()->keyBy('node_id');
        $nodes = Node::with('outboundStatuses.rule')->get();

        foreach ($nodes as $node) {
            $spell = $open->get($node->id);
            [$healthy, $reason, $unknown] = $this->assess($node);

            if ($healthy) {
                if ($spell) {
                    $spell->last_healthy_at = $now;
                    $spell->observations++;
                    if ($unknown) {
                        $spell->unknown_observations++;
                    }
                    // `[!]` 恢复了就把失败计数清零 —— 抖动过的区段仍是同一段，
                    // 不该因为中间闪了一下就断成两截（那会让"存活时间"被切碎）。
                    $spell->misses = 0;
                    $spell->first_miss_at = null;
                    $spell->save();
                    $confirmed++;
                } else {
                    NodeHealthSpell::create([
                        'node_id' => $node->id,
                        'role' => (string) ($node->role ?? 'unknown'),
                        'first_healthy_at' => $now,
                        // `[!!]` 观察开始时就已经活着的节点，真实起点在窗口之前
                        // 且不可知（左截断）。不标出来，这些区段的存活时间
                        // 会被系统性低估。
                        'left_truncated' => $this->alreadyAliveBeforeWeLooked($node),
                        'last_healthy_at' => $now,
                        'observations' => 1,
                        'unknown_observations' => $unknown ? 1 : 0,
                    ]);
                    $opened++;
                }

                continue;
            }

            if (! $spell) {
                continue;   // 本来就不在可用状态，没有区段可结束
            }

            $spell->misses++;
            $spell->first_miss_at ??= $now;
            if ($spell->misses < self::MISSES_TO_CLOSE) {
                $spell->save();

                continue;
            }
            // `[!]` ended_at 取【第一次失败采样】的时刻,不是检出时刻 ——
            // 真实失效发生在两次采样之间,取前者更接近。
            $spell->ended_at = $spell->first_miss_at;
            $spell->reason = $reason;
            $spell->outcome = NodeHealthSpell::isFailure($reason) ? 'failed' : 'censored';
            $spell->save();
            $closed++;
        }

        // 节点被删掉时，它的区段要按【删失】结束 —— 删除是运维动作，不是失效。
        $gone = $open->keys()->diff($nodes->pluck('id'));
        foreach ($gone as $id) {
            $s = $open->get($id);
            $s->ended_at = $s->last_healthy_at;
            $s->reason = 'deleted';
            $s->outcome = 'censored';
            $s->save();
            $closed++;
        }

        return ['opened' => $opened, 'closed' => $closed, 'confirmed' => $confirmed];
    }

    /**
     * 这个节点现在可不可用，不可用的话是什么原因。
     *
     * @return array{0:bool,1:string,2:bool}  [是否可用, 原因, 判定里是否含"未知"]
     */
    private function assess(Node $node): array
    {
        if (! $node->enabled) {
            return [false, 'manual', false];
        }

        $l = $this->layers->forNode($node);
        $unknown = collect($l)->contains(fn ($x) => $x['state'] === 'unknown');

        if ($l['relay']['state'] === 'bad') {
            return [false, $this->whyHeartbeatGone($node), $unknown];
        }
        if ($l['landing']['state'] === 'bad') {
            return [false, 'hop_failed', $unknown];
        }
        if ($l['dest']['state'] === 'bad') {
            return [false, 'dest_down', $unknown];
        }

        // `[!]` warn（劣化）算可用：节点确实还在服务，只是慢。
        // 把它算成失效会让"存活时间"变成"没有劣化的时间"，那是另一个量。
        return [true, '', $unknown];
    }

    /**
     * 心跳没了 —— 是机器没了，还是只有 agent 进程没了？
     *
     * `[!!]` 这个区分只在【面板本来就连得上那个端口】时成立。
     * accept_proxy 的落地按设计只对中转放行，面板连不过去是【正常的】，
     * 拿它推断"机器没了"会得到一个稳定错误的结论。这种情况报 unknown。
     */
    private function whyHeartbeatGone(Node $node): string
    {
        if ($node->accept_proxy_protocol || ! $node->port) {
            return 'unknown';
        }
        return $this->probe($node->server, (int) $node->port) ? 'agent_gone' : 'unreachable';
    }

    /**
     * 我们开始观察之前，它是不是已经活着了。
     *
     * `[!]` 判据是"这个节点此前有过心跳，而我们没有它的任何区段记录"——
     * 也就是它的存活早于本表的存在。
     */
    private function alreadyAliveBeforeWeLooked(Node $node): bool
    {
        if (! $node->last_heartbeat) {
            return false;
        }

        return ! NodeHealthSpell::where('node_id', $node->id)->exists();
    }
}
