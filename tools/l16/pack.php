<?php
// 进程 B：同一时刻，加油包到账
use App\Models\Plan;
use App\Models\User;

$at = (float) getenv('BARRIER');
while (microtime(true) < $at) {
    usleep(2000);
}
try {
    app(\App\Services\BillingService::class)->applyDataPack(
        User::findOrFail((int) getenv('UID')),
        Plan::findOrFail((int) getenv('PACK')),
    );
    echo "done\n";
} catch (\Throwable $e) {
    echo 'err: '.$e->getMessage()."\n";
}
