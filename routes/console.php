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
Schedule::command('traffic:reset-monthly')->dailyAt('00:05')->withoutOverlapping(120);

// 每 10 分钟激活到期的排队订单（当前套餐过期后自动生效）
//
// `[!!]` withoutOverlapping 是【第二层】，不是修复本身。
// 真正的修复在 BillingService::activate()：锁订单 + 锁内复查状态。
// 加这一层是因为上一轮没跑完就又起一轮时，两轮会同时看见同一批 queued 订单；
// 而且这里挡不住另一条路径 —— 用户点「立即结束当前套餐」也会调 activate()，
// 那跟调度器不是同一个进程，任何调度侧的锁都管不到。
// 参数是【分钟】：超时后自动释放，避免进程被 kill 掉后锁永远不放。
//
// `[!]` 下面几条同理：一次没跑完就再起一轮会重复做事的，都加上。
// 只读/幂等的（alive-ips:prune、stats:snapshot、logs:prune、health:sample）不加 ——
// 加了只是噪声，而且它们本来就该每轮都跑。
Schedule::command('orders:activate-due')->everyTenMinutes()->withoutOverlapping(30);

// 每 5 分钟支付对账：回调漏单则主动查单补发货
Schedule::command('payment:reconcile')->everyFiveMinutes()->withoutOverlapping(30);

// 每 10 分钟关闭超时未支付订单（关单前先查网关防误杀）
Schedule::command('orders:expire-pending')->everyTenMinutes()->withoutOverlapping(30);

// 每 10 分钟采样在线/日活写入 daily_stats（在线峰值累积当日最大），供历史趋势图
Schedule::command('stats:snapshot')->everyTenMinutes();

// 每日 9 点给 3 天内到期会员发到期提醒站内信（近 4 天去重，不重复轰炸）
Schedule::command('notify:expiry')->dailyAt('09:00');

// 每分钟把心跳失联(>180s)的节点置离线，避免死节点仍被下发给用户
Schedule::command('nodes:mark-offline')->everyMinute();

// 每日 4 点按保留天数清理日志/统计表(登录/崩溃/日流量),防磁盘无限增长
Schedule::command('logs:prune')->dailyAt('04:00');

// 每 5 分钟采样各节点是否可用,维护"存活区段"。
// `[!!]` 这是【被动观察】—— 不为了统计去杀节点。人工注入故障测出来的是
// "故障后的表现",不是"生产环境中的自然存活时间",而后者才是要回答的问题。
// 周期与心跳失联阈值(180s)同量级:更密没有信息增量,更疏会让失效时刻不准。
Schedule::command('health:sample')->everyFiveMinutes();
