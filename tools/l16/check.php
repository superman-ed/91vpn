<?php
use App\Models\User;

$u = User::findOrFail((int) getenv('UID'));
$gb = 1024 ** 3;
$q = (int) $u->transfer_enable;
printf("最终配额 = %.3f GB\n", $q / $gb);
printf("  起始 1GB + 加油包 10GB + 签到奖励(0.1~0.5GB) 都在的话，应当 > 11GB\n");
echo 'VERDICT='.($q > 11 * $gb ? 'KEPT' : 'LOST')."\n";
