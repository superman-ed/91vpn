<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Plan;
use App\Models\PromoChannel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PromoMockSeeder extends Seeder
{
    /** 推广代理 mock：几个代理 + 各自访问/注册/付费，呈现不同质量的漏斗 */
    public function run(): void
    {
        $plan = Plan::where('is_data_pack', false)->first()
            ?? Plan::create(['name' => 'VIP 月付', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100]);

        // [name, code, note, pv, uv, 注册数, 其中付费数, 每单价]
        $agents = [
            ['张三·TG频道', 'ZHANGSAN', 'tg @zhangsan', 1200, 860, 62, 15, 30],   // 量大、质量中
            ['李四·推特', 'LISI', 'twitter @lisi', 640, 470, 41, 18, 45],          // 中量、质量高
            ['王五·贴吧', 'WANGWU', '百度贴吧发帖', 2100, 1400, 33, 3, 30],        // 量大、质量差(可能刷量)
            ['赵六·朋友圈', 'ZHAOLIU', '微信朋友圈', 180, 150, 28, 12, 60],        // 小量、质量很高
            ['新渠道·待观察', 'NEWCH', '刚开始推', 90, 70, 4, 0, 30],              // 新、还没转化
        ];

        foreach ($agents as [$name, $code, $note, $pv, $uv, $regN, $paidN, $price]) {
            $ch = PromoChannel::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'note' => $note, 'enabled' => true, 'pv' => $pv, 'uv' => $uv],
            );

            for ($i = 0; $i < $regN; $i++) {
                $u = User::create([
                    'name' => Str::limit($code, 6, '').$i,
                    'email' => strtolower($code).$i.'@promo.local',
                    'password' => 'secret1234',   // User 的 hashed cast 会自动加密
                    'uuid' => (string) Str::uuid(),
                    'passwd' => Str::lower(Str::random(6)),
                    'ref_code' => Str::upper(Str::random(8)),
                    'invite_token' => Str::random(32),
                    'api_token' => Str::random(60),
                    'class' => $i < $paidN ? 1 : 0,
                    'class_expire' => $i < $paidN ? now()->addMonth() : now(),
                    'transfer_enable' => $i < $paidN ? 100 * 1024 ** 3 : 0,
                    'promo_code' => $code,
                ]);

                if ($i < $paidN) {
                    Order::create([
                        'user_id' => $u->id, 'plan_id' => $plan->id, 'amount' => $price,
                        'status' => 'paid', 'period' => 'month', 'pay_method' => 'epay',
                        'paid_at' => now()->subDays(random_int(0, 20)),
                    ]);
                }
            }
        }
    }
}
