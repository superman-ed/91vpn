<?php
// L-12 并发实验：造一个"下线充值 → 邀请人拿返利"的场景。
use App\Models\Recharge;
use App\Models\User;

User::where('email', 'like', 'l12-%@test.local')->delete();

$mk = function (string $tag, ?int $refBy = null) {
    $u = new User;
    $u->name = $tag;
    $u->email = "l12-{$tag}@test.local";
    $u->password = '$2y$12$abcdefghijklmnopqrstuv';
    $u->uuid = (string) \Illuminate\Support\Str::uuid();
    $u->passwd = 'l12';
    $u->invite_token = 'L12'.bin2hex(random_bytes(8));
    $u->money = 0;
    $u->ref_by = $refBy;
    $u->save();

    return $u;
};

$inviter = $mk('inviter');
$downline = $mk('downline', $inviter->id);

$r = Recharge::create([
    'order_no' => 'L12-'.bin2hex(random_bytes(4)),
    'user_id' => $downline->id,
    'amount' => 100.00,
    'status' => 'pending',
]);

echo "INVITER={$inviter->id} DOWNLINE={$downline->id} RECHARGE={$r->id}\n";
echo '返利比例: '.rebate_rate()."%\n";
