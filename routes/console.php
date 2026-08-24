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

// 每月 1 号 0 点刷新会员流量配额（清零已用 u/d）
Schedule::command('traffic:reset-monthly')->monthlyOn(1, '00:00');
