<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** 各周期天数(与 Admin\PlanController::PERIOD_DAYS 保持一致) */
    private const PERIOD_DAYS = ['month' => 30, 'quarter' => 90, 'half_year' => 180, 'year' => 365];

    /** 月付基准 × 月数 × 折扣。季 9 折 / 半年 85 折 / 年 8 折 —— 常见阶梯,可后台调价 */
    private const PERIOD_MULT = ['month' => [1, 1.0], 'quarter' => [3, 0.9], 'half_year' => [6, 0.85], 'year' => [12, 0.8]];

    public function run(): void
    {
        // 流量档(月付基准价)。每档补齐 月/季/半年/年 四个周期。
        $tiers = [
            ['name' => 'VIP①', 'base' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'sort' => 1],
            ['name' => 'VIP②', 'base' => 50, 'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200, 'ip_limit' => 7, 'sort' => 2],
            ['name' => 'VIP③', 'base' => 75, 'transfer_gb' => 500, 'class' => 3, 'speed_limit' => 300, 'ip_limit' => 9, 'sort' => 3],
        ];

        foreach ($tiers as $t) {
            foreach (self::PERIOD_MULT as $period => [$months, $discount]) {
                $price = (int) round($t['base'] * $months * $discount);
                Plan::updateOrCreate(
                    ['name' => $t['name'], 'period' => $period],
                    [
                        'name' => $t['name'],
                        'period' => $period,
                        'price' => $price,
                        'transfer_gb' => $t['transfer_gb'],
                        'class' => $t['class'],
                        'speed_limit' => $t['speed_limit'],
                        'ip_limit' => $t['ip_limit'],
                        'sort' => $t['sort'],
                        'duration_days' => self::PERIOD_DAYS[$period],
                        'reset_type' => 'monthly',
                        'is_data_pack' => false,
                        'on_sale' => true,
                        'stock' => -1,
                    ]
                );
            }
        }
    }
}
