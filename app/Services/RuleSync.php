<?php

namespace App\Services;

use App\Models\Node;
use Illuminate\Support\Carbon;

/**
 * 判断一个节点跑的是不是面板当下这一份规则。
 *
 * [!!] 这个类回答的问题，面板此前答不了：保存规则后界面说"已保存"，
 *      但那只是**面板存下了**。节点有没有拉到、拉到了认不认，全无迹象。
 *      真实发生过：下发缺 credential.uuid，节点每 10 秒拒绝一次、
 *      继续按旧规则转发 —— 服务正常、日志有话说，而运维面前一切正常，
 *      中转拓扑却和他以为的完全不同。
 *
 * [!!] 分得清"还没看到"和"看到了用不了"是本类的要点。两者的处理方式相反：
 *      前者去查网络和面板可达性，后者去改规则。只报一个"不一致"
 *      等于把运维推向一次盲目排查。
 */
class RuleSync
{
    /** 状态过期阈值：超过这么久没上报，就不再声称知道节点的状态。 */
    public const STALE_AFTER = 300;

    public const OK = 'ok';

    /**
     * 节点跑的是我们发出去的那份，但【有规则没发出去】。
     *
     * `[!]` 单独一档，不并进 OK 也不并进 BEHIND：
     *   并进 OK   → 就是此前那个静默盲区
     *   并进 BEHIND → 措辞会变成"节点落后了"，而节点没有落后，是面板没发
     * 归错档比没有档更糟：它把人引向错误的排查方向。
     */
    public const PARTIAL = 'partial';

    public const BEHIND = 'behind';

    public const DOWN = 'down';

    public const UNKNOWN = 'unknown';

    public const STALE = 'stale';

    /**
     * 计算一个节点的同步状态。
     *
     * @param  string  $expected  面板当下编译出的 config_hash
     * @return array{state:string,label:string,detail:string,error:?string}
     */
    public static function of(Node $node, string $expected, array $dropped = []): array
    {
        if (! $node->forwards()) {
            // 落地节点没有转发规则可言。返回 unknown 而不是 ok ——
            // 说"已同步"是在陈述一件不存在的事。
            return self::r(self::UNKNOWN, '不适用', '落地节点没有转发规则');
        }

        $at = $node->sync_reported_at ? Carbon::parse($node->sync_reported_at) : null;
        if ($at === null) {
            return self::r(self::UNKNOWN, '未上报',
                '节点从未报告过它在跑哪一份规则。可能是 agent 版本较旧，也可能从未连上。');
        }

        // [!!] 先判过期，再判内容。
        //
        // 节点失联之后，库里存着的是它**最后一次**报告的状态 —— 那可能是
        // "已同步"。照原样显示等于用一份陈旧快照声称节点跟得上，
        // 而它可能已经断了半天。宁可说"不知道"，也不要说一件不再为真的事。
        if ($at->diffInSeconds(now()) > self::STALE_AFTER) {
            return self::r(self::STALE, '状态过期',
                '最后一次报告在 '.$at->diffForHumans().'，节点可能已失联；'
                .'下面显示的是那一刻的状态，不代表现在。', $node->sync_error);
        }

        // 降级排在"落后"前面：它严重得多 —— 不是跑着旧规则，是**没在转发**。
        if ($node->sync_degraded) {
            return self::r(self::DOWN, '未在转发',
                '节点当前无法按任何一份规则启动内核，正在持续重试。'
                .'这条中转链路现在是断的。', $node->sync_error);
        }

        if ($node->applied_hash !== null && $node->applied_hash === $expected) {
            // `[!!]` 哈希一致只证明「节点跑的就是我们【发出去的】那份」，
            // 不证明「我配的规则都发出去了」—— 中间隔着一次编译，而编译会丢东西。
            // 丢掉的那些在哈希算出来【之前】就没了，所以两边永远一致。
            // 不单独报出来的话，这里就是一个说着实话却让人误解的绿灯。
            if ($dropped !== []) {
                $names = implode('、', array_map(
                    fn ($d) => "#{$d['id']}「{$d['name']}」", array_slice($dropped, 0, 3)));
                $more = count($dropped) > 3 ? ' 等 '.count($dropped).' 条' : '';

                return self::r(self::PARTIAL, '部分未下发',
                    '节点跑的确实是面板发出去的那份，但有规则【压根没发出去】：'
                    .$names.$more.'。它们解析不出可达的落地（落地停用/已删/端口无效），'
                    .'面板会整条跳过 —— 因为空出站会让节点拒绝整份配置。'
                    .'去规则页看「解析不出任何拨号目标」。');
            }

            return self::r(self::OK, '已同步',
                '节点正在跑的就是面板当下这一份（'.$node->sync_rules.' 条规则）。');
        }

        // [!!] 一份都没应用成功过 —— 那不是"落后"，是**没在转发**。
        //
        //      实测撞到：节点在规则不合法期间重启，内核从来没起来过，
        //      而它并没有"上一份"可退回。此时说"仍在用上一份转发"
        //      是在陈述一件不存在的事，会让运维以为链路还通着。
        if (($node->applied_hash ?? '') === '' && $node->sync_error) {
            return self::r(self::DOWN, '未在转发',
                '节点一份规则都没能应用（它也没有可退回的上一份），'
                .'这条中转链路现在是断的。', $node->sync_error);
        }

        // 到这里就是不一致。分清是哪一种。
        if ($node->fetched_hash !== null && $node->fetched_hash === $expected) {
            return self::r(self::BEHIND, '拒绝了新规则',
                '节点已经拉到了最新这一份，但没能应用，仍在用上一份转发。'
                .'这通常是规则内容的问题 —— 改规则，不是查网络。',
                $node->sync_error);
        }

        return self::r(self::BEHIND, '还没拉到',
            '节点报告的规则不是面板当下这一份，且它也没说自己拉到过。'
            .'通常是拉取失败（面板不可达、密钥不符），先查连通性。',
            $node->sync_error);
    }

    /**
     * 批量计算，供列表页使用。
     *
     * [!] 规则**只查一次**，然后给每个节点各编译一遍。逐节点调
     *     compileForNode() 会变成每页 N 次查询 —— 分页只是把 N+1 变成
     *     "每页 N+1"，不是解决。
     *
     * @param  iterable<Node>  $nodes
     * @return array<int,array{state:string,label:string,detail:string,error:?string}>
     */
    public static function forNodes(iterable $nodes, ForwardRuleService $svc): array
    {
        $preloaded = \App\Models\ForwardRule::with('outbounds')->get();
        $out = [];
        foreach ($nodes as $n) {
            $c = $svc->compileForNode($n, $preloaded);
            // `[!]` dropped 必须一起传:少传就退回到那个静默绿灯。
            $out[$n->id] = self::of($n, $c['config_hash'], $c['dropped'] ?? []);
        }

        return $out;
    }

    private static function r(string $s, string $label, string $detail, ?string $err = null): array
    {
        return ['state' => $s, 'label' => $label, 'detail' => $detail, 'error' => $err ?: null];
    }
}
