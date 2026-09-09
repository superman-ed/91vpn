<?php

namespace App\Http\Controllers\Admin;

use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\RuleAliveIp;
use Illuminate\Http\Request;

/**
 * 在线 IP。
 *
 * `[!!]` 这里回答的是「有多少个不同的来源在用这条中转」，**不是「谁在用」**。
 * 中转不认证用户（D-1），它看到的只有 TCP 连接的来源地址。
 */
class RelayOnlineIpController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $stale = now()->subMinutes(RuleAliveIp::STALE_MINUTES);

        // `[!!]` 先出【汇总】再出明细，不要一次把全部来源加载进来。
        // agent 侧每条规则的来源上限是 4096，几条规则就是上万行 ——
        // 而这一页九成的用途是"哪条规则有多少人在用"，明细是点进去才看的。
        $summary = RuleAliveIp::where('last_seen', '>=', $stale)
            ->selectRaw('rule_id, node_id, COUNT(*) AS n, MAX(last_seen) AS newest')
            ->groupBy('rule_id', 'node_id')
            ->orderByDesc('n')->get();

        $ruleNames = ForwardRule::pluck('name', 'id');
        $nodeNames = Node::pluck('name', 'id');

        // 只有点进某一组时才分页拉明细。
        $detail = null;
        $rid = (int) $request->query('rule', 0);
        $nid = (int) $request->query('node', 0);
        if ($rid > 0 && $nid > 0) {
            $detail = RuleAliveIp::where('rule_id', $rid)->where('node_id', $nid)
                ->where('last_seen', '>=', $stale)
                ->orderByDesc('last_seen')->paginate(100)->withQueryString();
        }

        return view('admin.relay-online-ip', [
            'summary' => $summary,
            'detail' => $detail,
            'rid' => $rid,
            'nid' => $nid,
            'ruleNames' => $ruleNames,
            'nodeNames' => $nodeNames,
            'total' => $summary->sum('n'),
            'staleMinutes' => RuleAliveIp::STALE_MINUTES,
        ]);
    }
}
