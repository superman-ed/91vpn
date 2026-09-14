<?php
// 模拟【一个忘了加锁的新调用方】：直接调 applyRecharge
use App\Models\User;
use App\Services\BillingService;

$at = (float) getenv('BARRIER');
while (microtime(true) < $at) {
    usleep(2000);
}
try {
    app(BillingService::class)->applyRecharge(User::findOrFail((int) getenv('DOWNLINE')), 100.00, 'T-DIRECT', '直接入账');
    echo "done\n";
} catch (\Throwable $e) {
    echo 'err: '.get_class($e).': '.$e->getMessage()."\n";
}
