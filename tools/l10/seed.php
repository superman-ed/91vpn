<?php
// L-10 并发实验：两个进程同时激活【同一笔】排队订单。
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

User::where('email','like','l10-%@test.local')->delete();
Plan::where('name','L10PLAN')->delete();
$plan = Plan::create(['name'=>'L10PLAN','price'=>30,'period'=>'month','transfer_gb'=>100,
  'reset_type'=>'monthly','class'=>3,'speed_limit'=>0,'ip_limit'=>0,'duration_days'=>30,
  'sort'=>0,'on_sale'=>true,'stock'=>-1,'is_data_pack'=>false]);

$u = new User;
$u->name='l10'; $u->email='l10-a@test.local'; $u->password='$2y$12$abcdefghijklmnopqrstuv';
$u->uuid=(string)\Illuminate\Support\Str::uuid(); $u->passwd='l10';
$u->invite_token='L10'.bin2hex(random_bytes(8));
$u->class=0; $u->class_expire=now()->addDays(9);      // 起点：剩 9 天
$u->transfer_enable=0; $u->base_transfer_enable=0;
$u->save();

$o = Order::create(['user_id'=>$u->id,'plan_id'=>$plan->id,'amount'=>30,
  'status'=>'queued','period'=>'month','order_no'=>'L10-'.bin2hex(random_bytes(3)),
  'activate_at'=>now()->subMinute()]);

echo "L10UID={$u->id} ORDER={$o->id}\n";
printf("起点：剩余 %.0f 天\n", now()->diffInDays($u->class_expire, false));
