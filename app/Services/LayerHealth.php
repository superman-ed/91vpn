<?php

namespace App\Services;

use App\Models\ForwardRule;
use App\Models\Node;

/**
 * 分层健康态：把「这个节点坏了」拆成「哪一层坏了」。
 *
 * `[!!]` 这个服务存在的理由，是 2026-09-13 实测到的一个形态：
 * 一台中转 `online=Y`、心跳 19 秒前、入站端口正常监听、面板一切正常，
 * 而它到落地那一跳【完全不通】（8 秒超时无回包，防火墙没放行）。
 * 订阅照样把它发给用户。面板当时能回答的只有 "Node = 在线"，
 * 而真正的答案是"中转层好的、落地层断了"。
 *
 * 三层的划分不是分类学，是**按谁有资格测**划的：
 *
 *   中转层   面板测得了      —— 心跳是节点往外发的
 *   落地层   【只有中转测得了】—— accept_proxy 的落地按设计只对中转放行，
 *                              面板连不过去是对的，不是故障
 *   dest 层  【只有落地测得了】—— dest 是第三方站点，且要从落地那台机器看
 *
 * 所以每一层的数据源都不同，且都不可互相替代。
 *
 * `[!]` 每一层只报**这一层自己能证明的东西**（同 NodeDiagnosis 的口径）。
 * 拿不到证据就是 unknown，不是 ok —— 一个错误的绿灯比没有灯更坏。
 */
class LayerHealth
{
    /** 心跳多久没来就算失联。与 NodeDiagnosis 一致。 */
    private const HEARTBEAT_STALE_SEC = 180;

    /**
     * 一个节点的三层状态。
     *
     * @return array<string,array{state:string,label:string,detail:string}>
     *   state: ok | warn | bad | unknown | na
     */
    public function forNode(Node $node): array
    {
        return [
            'relay' => $this->relayLayer($node),
            'landing' => $this->landingLayer($node),
            'dest' => $this->destLayer($node),
        ];
    }

    /** 整体结论 = 三层里最差的那个。na / unknown 不算坏。 */
    public function worst(array $layers): string
    {
        foreach (['bad', 'warn'] as $level) {
            foreach ($layers as $l) {
                if ($l['state'] === $level) {
                    return $level;
                }
            }
        }
        foreach ($layers as $l) {
            if ($l['state'] === 'unknown') {
                return 'unknown';
            }
        }

        return 'ok';
    }

    /** 中转层：这个节点自己还在不在。 */
    private function relayLayer(Node $node): array
    {
        $label = $node->role === 'relay' ? '中转' : '节点';
        if (! $node->last_heartbeat) {
            return $this->x('bad', $label, '从来没有收到过心跳');
        }
        $age = time() - (int) $node->last_heartbeat;
        if ($age > self::HEARTBEAT_STALE_SEC) {
            return $this->x('bad', $label, "失联 {$age} 秒");
        }

        return $this->x('ok', $label, "心跳 {$age} 秒前");
    }

    /**
     * 落地层：这个节点到它下游落地那一跳。
     *
     * `[!!]` 只对中转有意义。落地自己【不在这一层】—— 它就是这一层的终点，
     * 问"落地到落地通不通"没有含义。给落地节点返回 na 而不是 ok：
     * 假绿灯会让人以为查过了。
     */
    private function landingLayer(Node $node): array
    {
        if ($node->role !== 'relay' && $node->role !== 'both') {
            return $this->x('na', '到落地', '本节点不是中转');
        }

        $reported = $node->outboundStatuses->reject->stale();
        $fresh = $reported->filter->measured();
        if ($fresh->isEmpty()) {
            // `[!!]` 三种"没有证据"要分开说，因为对应三种完全不同的动作。
            if ($reported->isNotEmpty()) {
                return $this->x('unknown', '到落地',
                    '这些上游所在的规则【没开健康检查】—— 节点侧探测器不装配，'
                    .'上报的 alive 恒为真，那是"没人检查过"不是"活着"。去规则里打开健康检查');
            }
            $total = $node->outboundStatuses->count();

            return $this->x('unknown', '到落地', $total > 0
                ? "有 {$total} 条上报但都已过期 —— 中转多半停了，这些值不能当真"
                : '中转还没报过到落地的探测结果');
        }

        $dead = $fresh->reject->alive;
        if ($dead->isNotEmpty()) {
            $where = $dead->map(fn ($r) => $r->dial ?: $r->tag)->unique()->implode('、');
            return $this->x('bad', '到落地',
                "连不上 {$where} —— 这一跳是中转自己探的，面板测不了");
        }

        // `[!!]` 可达但明显变慢单独是一档。它与 alive 是两件事:
        // 每一项检查都绿,而每条用户连接都在多付那点时间。
        // 判据在节点侧且是【自身相对】的 —— 港→美 200ms 正常、同机房 1ms 也正常,
        // 面板这边不该拿绝对毫秒再判一次。
        $slow = $fresh->filter->slow;
        if ($slow->isNotEmpty()) {
            $d = $slow->map(fn ($r) => ($r->dial ?: $r->tag).' '.$r->delay_ms.'ms')->implode('、');
            return $this->x('warn', '到落地',
                "{$d} —— 相对它自己的基线明显变慢，加在每条用户连接上");
        }

        $ms = $fresh->max('delay_ms');

        return $this->x('ok', '到落地',
            $fresh->count().' 条上游全部可达'.($ms > 0 ? "，最慢 {$ms}ms" : ''));
    }

    /**
     * dest 层：REALITY 的 dest。
     *
     * `[!]` 劣化单独是一档：可达、每一项检查都绿，而每条用户新连接都在多付时间。
     */
    private function destLayer(Node $node): array
    {
        if (! $node->usesReality()) {
            return $this->x('na', 'dest', '本节点不跑 REALITY');
        }
        $h = $node->destHealth();
        if ($h === 'unknown') {
            return $this->x('unknown', 'dest', '没报过 dest 探活，或上报已过期');
        }
        if ($h === 'down') {
            return $this->x('bad', 'dest',
                "{$node->reported_dest} 连续失败 {$node->reported_dest_failures} 次 —— 端口照常监听，但没人能完成握手");
        }
        if ($node->reported_dest_degraded) {
            return $this->x('warn', 'dest',
                "{$node->reported_dest} 中位 {$node->reported_dest_latency_ms}ms —— 加在每条新连接上");
        }

        return $this->x('ok', 'dest', $node->reported_dest ?: '可达');
    }

    /**
     * 一条【服务路径】(中转 → 落地) 的落地层状态。
     *
     * `[!]` 与 landingLayer 的区别：这里只看指向【这一个落地】的那条规则。
     * 一台中转可以同时服务多个落地，其中一个断了不代表另一个也断。
     * 订阅要按路径筛，就必须按路径判。
     */
    public function hopState(Node $relay, ForwardRule $rule): string
    {
        return $relay->relayHopHealth($rule->id);
    }

    private function x(string $state, string $label, string $detail): array
    {
        return ['state' => $state, 'label' => $label, 'detail' => $detail];
    }
}
