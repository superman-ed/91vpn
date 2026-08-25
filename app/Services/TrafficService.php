<?php

namespace App\Services;

use App\Models\Node;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TrafficService
{
    /**
     * 记录并结算流量。每条 {user_id,u,d} 按节点倍率累加到用户。
     */
    public function record(Node $node, array $logs): int
    {
        $rate = (float) $node->traffic_rate;
        $count = 0;
        $rawU = 0;      // 本批原始流量(服务器真实带宽)
        $rawD = 0;
        $billedTotal = 0;

        DB::transaction(function () use ($node, $logs, $rate, &$count, &$rawU, &$rawD, &$billedTotal) {
            foreach ($logs as $log) {
                $userId = $log['user_id'] ?? null;
                $u = (int) ($log['u'] ?? 0);
                $d = (int) ($log['d'] ?? 0);
                if (! $userId || ($u === 0 && $d === 0)) {
                    continue;
                }

                $billedU = (int) round($u * $rate);
                $billedD = (int) round($d * $rate);
                $rawU += $u;
                $rawD += $d;
                $billedTotal += $billedU + $billedD;

                User::where('id', $userId)->update([
                    'u' => DB::raw("u + {$billedU}"),
                    'd' => DB::raw("d + {$billedD}"),
                    'transfer_today' => DB::raw('transfer_today + '.($billedU + $billedD)),
                    'last_used_at' => now(),
                ]);

                // 每日流量快照（累加当天）
                \App\Models\DailyTraffic::updateOrCreate(
                    ['user_id' => $userId, 'date' => now()->toDateString()],
                    []
                );
                \App\Models\DailyTraffic::where('user_id', $userId)
                    ->whereDate('date', now()->toDateString())
                    ->update([
                        'u' => DB::raw("u + {$billedU}"),
                        'd' => DB::raw("d + {$billedD}"),
                    ]);
                $count++;
            }

            // 节点每日流量汇总(原始 + 计费),供全站/节点带宽统计
            if ($rawU > 0 || $rawD > 0) {
                \App\Models\NodeDailyTraffic::firstOrCreate(
                    ['node_id' => $node->id, 'date' => now()->toDateString()],
                );
                \App\Models\NodeDailyTraffic::where('node_id', $node->id)
                    ->whereDate('date', now()->toDateString())
                    ->update([
                        'u' => DB::raw("u + {$rawU}"),
                        'd' => DB::raw("d + {$rawD}"),
                        'billed' => DB::raw("billed + {$billedTotal}"),
                    ]);
            }
        });

        return $count;
    }
}
