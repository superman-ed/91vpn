<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 中转节点的心跳遥测（ADR-008 补漏）。
 *
 * [!!] 迁移完成后核对时才发现：中转监控页要显示运行时长与负载，
 * 而本表没有这两列 —— 页面能打开、字段永远空。这类缺口不会报错，
 * 只会让一个功能"看起来在跑"。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->unsignedBigInteger('uptime_sec')->default(0)->after('last_heartbeat');
            $t->string('load', 32)->nullable()->after('uptime_sec');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->dropColumn(['uptime_sec', 'load']);
        });
    }
};
