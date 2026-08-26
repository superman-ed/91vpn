<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** 需记录心跳的定时任务 */
    public const WATCHED_TASKS = [
        'alive-ips:prune', 'traffic:reset-daily', 'traffic:reset-monthly',
        'orders:activate-due', 'payment:reconcile', 'orders:expire-pending', 'stats:snapshot', 'notify:expiry',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 记录定时任务最后运行时间(供系统健康监控),零侵入
        Event::listen(CommandFinished::class, function (CommandFinished $e) {
            if (in_array($e->command, self::WATCHED_TASKS, true)) {
                Cache::forever("task_hb:{$e->command}", ['at' => now()->timestamp, 'ok' => $e->exitCode === 0]);
            }
        });
    }
}
