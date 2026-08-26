<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
        // 相对时间全站用中文(diffForHumans 输出"5分钟前"而非"5 minutes ago")
        \Carbon\Carbon::setLocale('zh_CN');

        // 记录定时任务最后运行时间(供系统健康监控),零侵入
        Event::listen(CommandFinished::class, function (CommandFinished $e) {
            if (in_array($e->command, self::WATCHED_TASKS, true)) {
                Cache::forever("task_hb:{$e->command}", ['at' => now()->timestamp, 'ok' => $e->exitCode === 0]);
            }
        });

        // 统一的日期区间筛选:收敛各列表/导出里反复手写的 when(from)/when(to)+whereDate 样板
        $dateBetween = function ($from, $to, string $column = 'created_at') {
            /** @var EloquentBuilder|QueryBuilder $this */
            return $this->when($from, fn ($q) => $q->whereDate($column, '>=', $from))
                ->when($to, fn ($q) => $q->whereDate($column, '<=', $to));
        };
        EloquentBuilder::macro('dateBetween', $dateBetween);
        QueryBuilder::macro('dateBetween', $dateBetween);
    }
}
