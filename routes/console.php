<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每 5 分钟清理过期在线 IP，防止 alive_ips 表无限膨胀
Schedule::command('alive-ips:prune')->everyFiveMinutes();

// 每日 0 点清零今日已用流量
Schedule::command('traffic:reset-daily')->dailyAt('00:00');

// 每日 0 点检查：按各用户开通周年刷新流量配额（清零已用 u/d，推进下次刷新日）
Schedule::command('traffic:reset-monthly')->dailyAt('00:05');
