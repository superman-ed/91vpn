<?php

namespace App\Http\Controllers\Api\ModMu;

use App\Http\Controllers\Controller;
use App\Models\RuleAliveIp;
use App\Models\RuleOutboundStatus;
use App\Models\RuleTraffic;
use App\Services\ForwardRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 中转节点对接的 WebAPI（ADR-008：从 relaypanel 并入）。
 *
 * [!!] 与 ModMu\UserController 分开放：那边是【落地】的用户面
 * （名单、按用户流量、按用户在线 IP），这边是【中转】的规则面
 * （规则下发、按规则流量/来源 IP/上游状态、规则同步）。
 * D-1 把这两套数据分得很干净，控制器也别混在一起 ——
 * 混在一起时"哪个端点该不该给中转"就要靠人记。
 *
 * [!] 方法体从 relaypanel 原样搬入，注释一并保留。
 */
class ForwardController extends Controller
{
    public function __construct(private ForwardRuleService $rules) {}

    public function routes(Request $request)
    {
        $node = $request->attributes->get('node');
        abort_unless($node->forwards(), 404);

        return $this->ok($this->rules->compileForNode($node));
    }

    /**
     * GET /mod_mu/users —— 恒为空。
     *
     * [decided] D-1：中转不认证用户。这个端点存在只是因为 agent 在
     * 通用流程里会调它；返回空名单是"我这里没有用户"的正确表达，
     * 而不是让它拿到 404 后走错误路径。
     */

    public function ruleTraffic(Request $request)
    {
        $node = $request->attributes->get('node');
        $today = now()->toDateString();
        $n = 0;
        foreach ((array) $request->input('data', []) as $row) {
            $rid = (int) ($row['rule_id'] ?? 0);
            $u = max(0, (int) ($row['u'] ?? 0));
            $d = max(0, (int) ($row['d'] ?? 0));
            if ($rid <= 0 || ($u === 0 && $d === 0)) {
                continue;
            }
            $t = RuleTraffic::firstOrNew([
                'rule_id' => $rid, 'node_id' => $node->id, 'date' => $today,
            ]);
            $t->up += $u;
            $t->down += $d;
            $t->save();
            $n++;
        }

        return $this->ok(['accepted' => $n]);
    }

    /**
     * POST /mod_mu/nodes/{node}/rules/aliveip —— 按转发规则的在线来源 IP。
     *
     * [decided] D-1：中转不认证用户，收到的【只有 IP、没有身份】。
     *
     * [!!] upsert 而不是 insert：这是当前状态不是流水。同一个
     * (规则, 节点, IP) 只留一行，反复上报只刷新 last_seen ——
     * 存成流水的话，一个挂了一天的连接会产生一千多行，表达的还是同一件事。
     */

    public function ruleAliveIp(Request $request)
    {
        $node = $request->attributes->get('node');
        $now = now();
        $rows = [];
        foreach ((array) $request->input('data', []) as $item) {
            $rid = (int) ($item['rule_id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            foreach ((array) ($item['ips'] ?? []) as $ip) {
                $ip = trim((string) $ip);
                // [!] 校验一下再落库：这是节点报上来的数据，而 ip 会被渲染
                // 到管理界面上。长度限死在字段宽度内，非法形态直接丢。
                if ($ip === '' || strlen($ip) > 45 || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    continue;
                }
                $rows[] = [
                    'rule_id' => $rid, 'node_id' => $node->id, 'ip' => $ip,
                    'last_seen' => $now, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        if ($rows !== []) {
            RuleAliveIp::upsert($rows, ['rule_id', 'node_id', 'ip'], ['last_seen', 'updated_at']);
        }

        // [!] 顺手清掉过期的。放在这里而不是定时任务：上报本身就是"心跳"，
        // 没有上报就说明节点掉了，那时也不需要清 —— 页面按 last_seen
        // 过滤即可。多一个 cron 就多一个会忘记装的东西。
        RuleAliveIp::where('node_id', $node->id)
            ->where('last_seen', '<', $now->copy()->subMinutes(RuleAliveIp::STALE_MINUTES * 4))
            ->delete();

        return $this->ok(['accepted' => count($rows)]);
    }

    /**
     * POST /mod_mu/nodes/{node}/rules/status —— 上游的存活与活跃连接数。
     *
     * [!] 覆盖而不是追加：这是当前状态不是历史。要看趋势是另一件事
     * （需要时序存储），而这一页要回答的是"现在哪个落地挂了"。
     */

    public function ruleStatus(Request $request)
    {
        $node = $request->attributes->get('node');
        $now = now();
        $rows = [];
        foreach ((array) $request->input('data', []) as $item) {
            $rid = (int) ($item['rule_id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            foreach ((array) ($item['outbounds'] ?? []) as $o) {
                $tag = substr((string) ($o['tag'] ?? ''), 0, 64);
                if ($tag === '') {
                    continue;
                }
                $rows[] = [
                    'rule_id' => $rid, 'node_id' => $node->id, 'tag' => $tag,
                    'dial' => substr((string) ($o['dial'] ?? ''), 0, 255) ?: null,
                    'backup' => ! empty($o['backup']),
                    'alive' => ! empty($o['alive']),
                    // [!] live 夹到非负：它是无符号列，节点报个负数会让整批写入失败，
                    // 而那会让【所有】上游的状态一起停止更新。
                    'live' => max(0, (int) ($o['live'] ?? 0)),
                    'reported_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        if ($rows !== []) {
            RuleOutboundStatus::upsert($rows, ['rule_id', 'node_id', 'tag'],
                ['dial', 'backup', 'alive', 'live', 'reported_at', 'updated_at']);
        }

        return $this->ok(['accepted' => count($rows)]);
    }

    /**
     * POST /mod_mu/nodes/{node}/rules/sync —— 节点报告它现在跑的是哪一份规则。
     *
     * [!!] 这是面板此前答不了的那个问题：**你刚保存的规则，节点认了吗？**
     *      从前保存完只说"已保存"，节点若因校验失败一直用旧规则跑，
     *      服务正常、界面无异常 —— 而中转拓扑其实和你以为的不一样。
     *
     * [!] 覆盖而不是追加：这是当前状态。要看"什么时候开始落后的"是另一件事
     *     （需要时序存储），而这一页要回答的是"现在跟上了没有"。
     */

    public function ruleSync(Request $request)
    {
        $node = $request->attributes->get('node');

        // [!] 哈希只做长度和字符集的形状检查，不去核对它"是不是我发过的"。
        //     节点报一个我们没见过的哈希是**合法且有意义**的：它可能跑着
        //     上一版面板下发的规则。那正好显示成"落后"，而不是被丢弃 ——
        //     丢弃会让这个节点看起来"从未上报"，与真的失联无法区分。
        $hash = fn (string $k) => preg_match('/^[a-f0-9]{0,32}$/',
            (string) $request->input($k, '')) ? (string) $request->input($k, '') : '';

        $node->forceFill([
            'applied_hash' => $hash('applied_hash') ?: null,
            'fetched_hash' => $hash('fetched_hash') ?: null,
            // [!] 截断到 2000 字：内核的构建错误可以很长，但整段塞进
            //     列表页会把界面撑爆。留足够看清是哪一类问题即可。
            'sync_error' => mb_substr((string) $request->input('error', ''), 0, 2000) ?: null,
            'sync_degraded' => (bool) $request->input('degraded', false),
            'sync_rules' => max(0, (int) $request->input('rules', 0)),
            'sync_reported_at' => now(),
        ])->save();

        return $this->ok([]);
    }

    private function ok(array $data)
    {
        return response()->json(['ret' => 1, 'data' => $data, 'msg' => 'ok']);
    }
}
