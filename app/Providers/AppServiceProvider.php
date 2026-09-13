<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
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

    /**
     * 会写库、且最常见用法是"临时验一件事"的命令 —— 在生产库上一律拒绝。
     *
     * `[!]` migrate（前向）不在此列:它是正常部署路径,拦住它等于堵死上线。
     * migrate:fresh / :reset / db:wipe 在此列:它们会【删数据】。
     */
    private const GUARDED_COMMANDS = [
        'tinker',
        'db:seed',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    public function boot(): void
    {
        // 相对时间全站用中文(diffForHumans 输出"5分钟前"而非"5 minutes ago")
        \Carbon\Carbon::setLocale('zh_CN');

        // `[!!]` 挡住"人手一抖就写了生产库"的那几条命令。
        //
        // 起因:2026-09-13 我为了验一条检查,用 `php artisan tinker --execute`
        // 往【生产】nodes 表加了一列。tools/repro 的守卫拦得住 tools/repro,
        // 拦不住直接调 artisan —— 而判据写下来不等于会被遵守,
        // 守卫要挡在路上,不能只写在文档里。
        //
        // `[!]` 刻意【只挡这几条】,不是所有命令:
        //   migrate / 定时任务 本来就该在生产库上跑,一律拦住等于把部署也堵死,
        //   而一个挡住正常操作的守卫,三天之内就会被人加 --force 绕过去。
        // 逃生口是 REPRO_ALLOW_REAL=1(与 tools/repro --real 同一个开关)。
        Event::listen(CommandStarting::class, function (CommandStarting $e) {
            if (! in_array($e->command, self::GUARDED_COMMANDS, true)) {
                return;
            }
            if (getenv('REPRO_ALLOW_REAL') === '1' || app()->environment('testing')) {
                return;
            }
            $db = \DB::connection()->getDatabaseName();
            if (str_ends_with((string) $db, '_test')) {
                return;
            }

            throw new \RuntimeException(
                "✋ 拒绝在库「{$db}」上执行 {$e->command}。\n\n"
                ."   这几条命令会写库,而它们最常见的用法是临时验一件事 ——\n"
                ."   一旦连错库,现象是【没有现象】:命令正常跑完,数据静静地进了生产。\n\n"
                ."   跑复现/造数脚本:  tools/repro 脚本.php        (自动落在 vpn_test)\n"
                ."   确实要动生产:      REPRO_ALLOW_REAL=1 再执行  (或 tools/repro --real)\n"
            );
        });

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
