<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class MockUsersSeeder extends Seeder
{
    private const GB = 1024 ** 3;

    public function run(): void
    {
        $created = [];

        for ($i = 0; $i < 50; $i++) {
            $roll = rand(1, 100);
            $createdAt = now()->subDays(rand(0, 180))->subMinutes(rand(0, 1440));

            if ($roll <= 45) {
                // 生效会员
                $class = rand(1, 3);
                $quotaGb = [50, 100, 300, 500][array_rand([50, 100, 300, 500])];
                $quota = $quotaGb * self::GB;
                $used = (int) ($quota * (rand(0, 90) / 100));
                $attr = [
                    'class' => $class,
                    'transfer_enable' => $quota,
                    'base_transfer_enable' => $quota,
                    'u' => (int) ($used * 0.2), 'd' => (int) ($used * 0.8),
                    'class_expire' => now()->addDays(rand(1, 330)),
                    'next_reset_at' => now()->addDays(rand(1, 30)),
                    'node_speed_limit' => [0, 100, 200][array_rand([0, 100, 200])],
                    'node_ip_limit' => [0, 3, 5][array_rand([0, 3, 5])],
                ];
            } elseif ($roll <= 65) {
                // 已过期(曾是会员)
                $attr = [
                    'class' => rand(1, 2),
                    'transfer_enable' => 100 * self::GB, 'base_transfer_enable' => 100 * self::GB,
                    'u' => rand(10, 90) * self::GB, 'd' => 0,
                    'class_expire' => now()->subDays(rand(1, 120)),
                ];
            } elseif ($roll <= 90) {
                // 免费用户(未开通)
                $attr = ['class' => 0, 'class_expire' => now()->subDay()];
            } else {
                // 封禁
                $attr = [
                    'class' => rand(0, 2),
                    'transfer_enable' => 100 * self::GB, 'base_transfer_enable' => 100 * self::GB,
                    'class_expire' => now()->addDays(rand(-30, 200)),
                    'banned' => true,
                ];
            }

            $attr['money'] = rand(0, 30000) / 100;   // 0 ~ 300
            $attr['created_at'] = $createdAt;
            $attr['updated_at'] = $createdAt;
            // 三成用户挂在已有用户名下(邀请关系)
            if ($created && rand(1, 10) <= 3) {
                $attr['ref_by'] = $created[array_rand($created)];
            }
            if (rand(1, 100) <= 40) {
                $attr['last_used_at'] = now()->subMinutes(rand(0, 4320));
            }

            $created[] = User::factory()->create($attr)->id;
        }

        $this->command?->info('已生成 '.count($created).' 个 mock 用户');
    }
}
