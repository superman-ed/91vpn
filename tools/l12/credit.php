<?php
// 走【现有的受保护入口】：creditRecharge(行锁 + 状态复查)
use App\Models\Recharge;
use App\Services\BillingService;

$at = (float) getenv('BARRIER');           // 两个进程对表，尽量真正并发
while (microtime(true) < $at) {
    usleep(2000);
}
try {
    app(BillingService::class)->creditRecharge(Recharge::findOrFail((int) getenv('RECHARGE')), 'T-RETRY');
    echo "done\n";
} catch (\Throwable $e) {
    echo 'err: '.get_class($e).': '.$e->getMessage()."\n";
}
