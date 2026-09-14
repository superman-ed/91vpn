<?php
// 一个进程：激活这笔订单
use App\Models\Order;
use App\Services\BillingService;

$at = (float) getenv('BARRIER');
while (microtime(true) < $at) { usleep(2000); }
try {
    $ok = app(BillingService::class)->activate(Order::findOrFail((int) getenv('ORDER')));
    echo ($ok ? "delivered\n" : "skipped\n");
} catch (\Throwable $e) {
    echo 'err: '.get_class($e).': '.$e->getMessage()."\n";
}
