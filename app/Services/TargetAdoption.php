<?php

namespace App\Services;

use App\Models\ForwardRule;
use App\Models\Node;

/**
 * 把出站里写死的地址，收编成对应落地节点的引用。
 *
 * `[!!]` 这个动作【不改变下发给节点的内容】—— 节点引用是面板侧解析成
 * host:port 的（ForwardRuleService::resolveTargets），agent 根本看不到节点 ID。
 * 所以它是纯粹的面板侧收编：之后「到落地」那一层的健康态、PROXY 头配对校验、
 * 落地换地址时自动跟随，才有东西可依。
 *
 * `[!]` 逻辑放在服务里而不是控制器里，是为了能在别处照原样跑一遍 ——
 * 对生产配置动手之前，要能先用同一段代码验证「转换前后 config_hash 不变」。
 * 逻辑困在控制器里的话，验证就只能靠另写一份，而另写的那份证明不了什么。
 */
class TargetAdoption
{
    /**
     * @return array{done:array<int,string>,skipped:array<int,string>}
     */
    public function run(ForwardRule $rule): array
    {
        $rule->loadMissing('outbounds');
        $done = [];
        $skipped = [];

        foreach ($rule->outbounds as $o) {
            if (! $o->target_addr || (is_array($o->target_node_set) && $o->target_node_set !== [])) {
                continue;
            }
            $hits = Node::where('server', $o->target_addr)
                ->when($o->target_port, fn ($q) => $q->where('port', (int) $o->target_port))
                ->get();

            // `[!!]` 只转【能唯一对上】的。对上多台（同 IP 同端口两台落地）或
            // 对不上的一律不动 —— 猜一个填进去比留着写死的地址更糟：
            // 那会让面板从"认不出来"变成"认错了人"，而认错了是不会报错的。
            if ($hits->count() !== 1) {
                $skipped[] = $o->target_addr.'（'
                    .($hits->isEmpty() ? '面板里没有这台节点' : "对上了 {$hits->count()} 台，分不清是哪台").'）';

                continue;
            }
            $n = $hits->first();
            $before = ['target_node_set' => $o->target_node_set, 'target_addr' => $o->target_addr];
            // `[!]` 地址字段【留着】不清空：万一之后要回退，那是唯一的原始记录。
            // 节点集优先，所以留着它不影响下发（resolveTargets 先看节点集）。
            $o->target_node_set = [$n->id];
            $o->save();
            Audit::log('adopt_target', 'forward_outbound', $o->id,
                "规则 #{$rule->id} 出站 #{$o->id}", $before,
                ['target_node_set' => [$n->id], 'target_addr' => $o->target_addr]);
            $done[] = "{$o->target_addr} → #{$n->id}「{$n->name}」";
        }

        return ['done' => $done, 'skipped' => $skipped];
    }
}
