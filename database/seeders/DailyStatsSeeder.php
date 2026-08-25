<?php

namespace Database\Seeders;

use App\Models\DailyStat;
use Illuminate\Database\Seeder;

class DailyStatsSeeder extends Seeder
{
    /** 造近 30 日在线/日活趋势 mock 数据（演示用；真实数据由 stats:snapshot 采集） */
    public function run(): void
    {
        $base = 40;
        for ($i = 29; $i >= 0; $i--) {
            $date = today()->subDays($i);
            // 周末略高 + 缓慢增长趋势 + 波动
            $growth = (int) ((29 - $i) * 1.2);
            $weekend = in_array($date->dayOfWeek, [0, 6], true) ? 12 : 0;
            $wave = (int) (sin($i / 2.0) * 8);
            $dau = max(1, $base + $growth + $weekend + $wave);
            $peak = (int) round($dau * (0.32 + ($i % 5) * 0.02));   // 峰值在线约为日活的 1/3
            $new = max(0, (int) round($dau * 0.08) + ($i % 3) - 1);

            DailyStat::updateOrCreate(
                ['date' => $date->toDateString()],
                ['dau' => $dau, 'peak_online' => $peak, 'new_users' => $new],
            );
        }
    }
}
