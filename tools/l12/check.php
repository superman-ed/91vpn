<?php
use App\Models\BalanceLog;
use App\Models\Payback;
use App\Models\User;

$inv = User::find((int) getenv('INVITER'));
$dn = User::find((int) getenv('DOWNLINE'));
$paybacks = Payback::where('user_id', $inv->id)->count();
$rebateLogs = BalanceLog::where('user_id', $inv->id)->where('type', 'rebate')->count();
$rechargeLogs = BalanceLog::where('user_id', $dn->id)->where('type', 'recharge')->count();
$expectRebate = round(100.00 * rebate_rate() / 100, 2);

printf("下线余额      = %.2f  （一次充值应为 100.00）\n", $dn->money);
printf("邀请人余额    = %.2f  （一次返利应为 %.2f，比例 %s%%）\n", $inv->money, $expectRebate, rebate_rate());
printf("paybacks      = %d 条\n", $paybacks);
printf("rebate 流水   = %d 条\n", $rebateLogs);
printf("recharge 流水 = %d 条\n", $rechargeLogs);

$once = abs((float) $dn->money - 100.00) < 0.001
    && abs((float) $inv->money - $expectRebate) < 0.001
    && $paybacks === 1 && $rebateLogs === 1 && $rechargeLogs === 1;
echo 'VERDICT='.($once ? 'ONCE' : 'DOUBLE')."\n";
