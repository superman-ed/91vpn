<?php

namespace App\Services;

use App\Models\Node;
use Illuminate\Support\Facades\DB;

class TrafficService
{
    /**
     * 记录并结算流量。每条 {user_id,u,d} 按节点倍率累加到用户。
     *
     * 批量写入(规模化):先在内存聚合同批同用户，再用
     *   - users：一条 CASE WHEN 批量累加(分块 500)
     *   - daily_traffic / node_daily_traffic：INSERT ... ON DUPLICATE KEY UPDATE 累加
     * 把 3N 次查询压到常数级，支撑多节点高频上报。
     */
    public function record(Node $node, array $logs): int
    {
        $rate = (float) $node->traffic_rate;
        $date = now()->toDateString();
        $now = now()->toDateTimeString();

        // 先做输入清洗:丢弃非正 user_id、负数流量(防"负数续量"白嫖 / buggy agent 冲账),
        // 记下候选 user_id 供归属校验
        $clean = [];
        $rejected = 0;
        foreach ($logs as $log) {
            $userId = (int) ($log['user_id'] ?? 0);
            $u = (int) ($log['u'] ?? 0);
            $d = (int) ($log['d'] ?? 0);
            if ($userId <= 0 || $u < 0 || $d < 0) {
                $rejected++;                 // 负数/非法一律拒收,不入账
                continue;
            }
            if ($u === 0 && $d === 0) {
                continue;
            }
            $clean[] = [$userId, $u, $d];
        }
        if ($rejected > 0) {
            \Illuminate\Support\Facades\Log::warning("[流量上报] 节点 {$node->id} 丢弃 {$rejected} 条非法/负数记录");
        }
        if (! $clean) {
            return 0;
        }

        // 归属校验:只接受"确实存在、未封禁、且等级够服务本节点"的用户,
        // 防持节点密钥者给不相干用户乱报流量把其顶爆。用 class(非 transfer_enable/到期)
        // 判定,避免误伤到期/耗尽用户的最后一笔正常流量。
        $candidateIds = array_values(array_unique(array_map(fn ($r) => $r[0], $clean)));
        $allowed = DB::table('users')
            ->whereIn('id', $candidateIds)
            ->where('banned', false)
            ->where('class', '>=', (int) $node->node_class)
            ->pluck('id')
            ->flip();

        // 内存聚合：user_id => [billedU, billedD]（同批同用户合并）
        $perUser = [];
        $rawU = 0;
        $rawD = 0;
        $billedTotal = 0;

        foreach ($clean as [$userId, $u, $d]) {
            if (! isset($allowed[$userId])) {
                continue;               // 非本节点可服务用户,跳过
            }
            $billedU = (int) round($u * $rate);
            $billedD = (int) round($d * $rate);
            $rawU += $u;
            $rawD += $d;
            $billedTotal += $billedU + $billedD;

            $slot = $perUser[$userId] ?? [0, 0];
            $slot[0] += $billedU;
            $slot[1] += $billedD;
            $perUser[$userId] = $slot;
        }

        if (! $perUser) {
            return 0;
        }

        DB::transaction(function () use ($node, $perUser, $rawU, $rawD, $billedTotal, $date, $now) {
            $this->bulkUpdateUsers($perUser, $now);
            $this->bulkUpsertDailyTraffic($perUser, $date, $now);

            // 节点每日流量汇总(原始 + 计费)
            DB::statement(
                'INSERT INTO node_daily_traffic (node_id, date, u, d, billed, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE u = u + VALUES(u), d = d + VALUES(d), billed = billed + VALUES(billed), updated_at = VALUES(updated_at)',
                [$node->id, $date, $rawU, $rawD, $billedTotal, $now, $now],
            );
        });

        return count($perUser);
    }

    /** users 表批量累加已用流量：一条 CASE WHEN 覆盖多用户，超 500 分块 */
    private function bulkUpdateUsers(array $perUser, string $now): void
    {
        foreach (array_chunk($perUser, 500, true) as $chunk) {
            $ids = array_keys($chunk);
            $caseU = $caseD = $caseT = '';
            foreach ($chunk as $uid => [$bu, $bd]) {
                $uid = (int) $uid;
                $total = $bu + $bd;
                $caseU .= " WHEN {$uid} THEN u + {$bu}";
                $caseD .= " WHEN {$uid} THEN d + {$bd}";
                $caseT .= " WHEN {$uid} THEN transfer_today + {$total}";
            }
            $in = implode(',', array_map('intval', $ids));
            DB::update(
                "UPDATE users SET
                    u = CASE id{$caseU} END,
                    d = CASE id{$caseD} END,
                    transfer_today = CASE id{$caseT} END,
                    last_used_at = ?
                 WHERE id IN ({$in})",
                [$now],
            );
        }
    }

    /** daily_traffic 批量累加(按用户按天)：INSERT ... ON DUPLICATE KEY UPDATE，分块 500 */
    private function bulkUpsertDailyTraffic(array $perUser, string $date, string $now): void
    {
        foreach (array_chunk($perUser, 500, true) as $chunk) {
            $placeholders = [];
            $bindings = [];
            foreach ($chunk as $uid => [$bu, $bd]) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?)';
                array_push($bindings, (int) $uid, $date, $bu, $bd, $now, $now);
            }
            DB::statement(
                'INSERT INTO daily_traffic (user_id, date, u, d, created_at, updated_at) VALUES '
                .implode(',', $placeholders)
                .' ON DUPLICATE KEY UPDATE u = u + VALUES(u), d = d + VALUES(d), updated_at = VALUES(updated_at)',
                $bindings,
            );
        }
    }
}
