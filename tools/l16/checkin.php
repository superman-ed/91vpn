<?php
// 进程 A：这个用户签到（走真实控制器路径）
use App\Models\User;

$at = (float) getenv('BARRIER');
while (microtime(true) < $at) {
    usleep(2000);
}
$u = User::findOrFail((int) getenv('UID'));
auth()->login($u);
try {
    app(\App\Http\Controllers\User\CheckinController::class)->store();
    echo "done\n";
} catch (\Throwable $e) {
    echo 'err: '.$e->getMessage()."\n";
}
