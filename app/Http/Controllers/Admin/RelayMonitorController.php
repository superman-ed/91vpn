<?php

namespace App\Http\Controllers\Admin;

use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\RuleAliveIp;
use App\Models\RuleOutboundStatus;
use Illuminate\Support\Facades\DB;

/**
 * 节点监控。
 *
 * `[!!]` 这一页混着两种时间尺度的数据，页面上必须分清楚，
 * 否则运维会拿"按天累计"去判断"现在通不通"：
 *
 *   实时（秒级，快照）   上游存活、活跃连接数、在线来源数
 *   按天累计             整机流量、规则流量
 *
 * 快照类的数据都带 `reported_at`，太旧就不能当真 —— 页面显示它有多旧，
 * 而不是让人误以为"显示存活 = 现在还活着"。
 */
class RelayMonitorController extends \App\Http\Controllers\Controller
{
    /** @see \App\Models\Node::STALE_SEC —— 唯一来源,别在这里另写一个数 */
    private const STALE_SEC = Node::STALE_SEC;

    public function index()
    {
        $nodes = Node::orderBy('id')->get();
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $rows = DB::table('node_net_traffic')
            ->whereIn('date', $days)
            ->select('node_id', 'date', DB::raw('up + down AS bytes'))
            ->get();
        $traffic = [];
        foreach ($rows as $row) {
            $traffic[(int) $row->node_id][(string) $row->date] = (int) $row->bytes;
        }

        // 实时部分：上游状态 + 在线来源数，按 (规则, 节点) 归拢。
        $status = RuleOutboundStatus::orderBy('tag')->get()->groupBy(
            fn ($s) => $s->rule_id.'-'.$s->node_id
        );
        $sources = RuleAliveIp::where('last_seen', '>=', now()->subMinutes(RuleAliveIp::STALE_MINUTES))
            ->selectRaw('rule_id, node_id, COUNT(*) AS n')
            ->groupBy('rule_id', 'node_id')->get()
            ->keyBy(fn ($r) => $r->rule_id.'-'.$r->node_id);

        return view('admin.relay-monitor', [
            'nodes' => $nodes,
            'days' => $days,
            'traffic' => $traffic,
            'staleSec' => self::STALE_SEC,
            'now' => time(),
            'status' => $status,
            'sources' => $sources,
            'ruleNames' => ForwardRule::pluck('name', 'id'),
            'nodeNames' => $nodes->pluck('name', 'id'),
            'statusStale' => RuleOutboundStatus::STALE_MINUTES,
        ]);
    }
}
