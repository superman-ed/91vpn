<?php
use App\Models\Order;
use App\Models\User;

$u = User::findOrFail((int) getenv('L10UID'));
$days = (int) round(now()->diffInDays($u->class_expire, false));
$order = Order::findOrFail((int) getenv('ORDER'));
printf("剩余天数 = %d   （起点 9 天 + 一个 30 天套餐 = 39；发两次则 69）\n", $days);
printf("订单状态 = %s\n", $order->status);
echo 'VERDICT='.($days <= 40 ? 'ONCE' : 'DOUBLE')."\n";
