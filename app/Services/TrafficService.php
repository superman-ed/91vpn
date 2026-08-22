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

        DB::transaction(function () use ($node, $logs, $rate, &$count) {
            foreach ($logs as $log) {
                $userId = $log['user_id'] ?? null;
                $u = (int) ($log['u'] ?? 0);
                $d = (int) ($log['d'] ?? 0);
                if (! $userId || ($u === 0 && $d === 0)) {
                    continue;
                }

                $billedU = (int) round($u * $rate);
                $billedD = (int) round($d * $rate);

                User::where('id', $userId)->update([
                    'u' => DB::raw("u + {$billedU}"),
                    'd' => DB::raw("d + {$billedD}"),
                    'transfer_today' => DB::raw('transfer_today + '.($billedU + $billedD)),
                ]);
                $count++;
            }
        });

        return $count;
    }
}
