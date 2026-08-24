<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class DataPackSeeder extends Seeder
{
    public function run(): void
    {
        // 真站三档流量叠加包：立即生效、随套餐结束作废
        $packs = [
            ['name' => '50GB 流量叠加包', 'price' => 15, 'transfer_gb' => 50],
            ['name' => '100GB 流量叠加包', 'price' => 30, 'transfer_gb' => 100],
            ['name' => '300GB 流量叠加包', 'price' => 50, 'transfer_gb' => 300],
        ];

        foreach ($packs as $i => $p) {
            Plan::updateOrCreate(
                ['name' => $p['name']],
                [
                    'price' => $p['price'],
                    'transfer_gb' => $p['transfer_gb'],
                    'is_data_pack' => true,
                    'period' => 'month',
                    'duration_days' => 0,
                    'class' => 0,
                    'speed_limit' => 0,
                    'ip_limit' => 0,
                    'reset_type' => 'monthly',
                    'on_sale' => true,
                    'sort' => 100 + $i,
                ]
            );
        }
    }
}
