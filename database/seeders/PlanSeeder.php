<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'VIP①', 'price' => 30, 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'sort' => 1],
            ['name' => 'VIP②', 'price' => 50, 'transfer_gb' => 300, 'class' => 2, 'speed_limit' => 200, 'ip_limit' => 7, 'sort' => 2],
            ['name' => 'VIP③', 'price' => 75, 'transfer_gb' => 500, 'class' => 3, 'speed_limit' => 300, 'ip_limit' => 9, 'sort' => 3],
        ];
        foreach ($plans as $p) {
            Plan::updateOrCreate(
                ['name' => $p['name'], 'period' => 'month'],
                array_merge($p, ['period' => 'month', 'duration_days' => 30, 'on_sale' => true, 'stock' => -1])
            );
        }
    }
}
