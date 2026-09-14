<?php
// L-16 并发实验：签到 与 加油包到账 撞在一起。
use App\Models\Plan;
use App\Models\User;

User::where('email', 'like', 'l16-%@test.local')->delete();
Plan::where('name', 'L16PACK')->delete();

$mk = function (string $tag, int $class, ?\Carbon\Carbon $exp) {
    $u = new User;
    $u->name = $tag;
    $u->email = "l16-{$tag}@test.local";
    $u->password = '$2y$12$abcdefghijklmnopqrstuv';
    $u->uuid = (string) \Illuminate\Support\Str::uuid();
    $u->passwd = 'l16';
    $u->invite_token = 'L16'.bin2hex(random_bytes(8));
    $u->class = $class;
    $u->class_expire = $exp;
    $u->transfer_enable = 1 * 1024 ** 3;   // 起始 1GB
    $u->base_transfer_enable = 1 * 1024 ** 3;
    $u->last_check_in = 0;
    $u->save();

    return $u;
};

// free = 非会员(走绝对值分支)  member = 有效会员(走 SQL 自增分支)
$free = $mk('free', 0, null);
$member = $mk('member', 3, now()->addYear());

$pack = Plan::create([
    'name' => 'L16PACK', 'price' => 1, 'period' => 'month', 'transfer_gb' => 10,
    'reset_type' => 'monthly', 'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0,
    'duration_days' => 30, 'sort' => 0, 'on_sale' => true, 'stock' => -1,
    'is_data_pack' => true,
]);

echo "FREE={$free->id} MEMBER={$member->id} PACK={$pack->id}\n";
