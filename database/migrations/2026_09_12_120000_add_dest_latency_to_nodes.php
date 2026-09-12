<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dest 的时延趋势与劣化标记。
 *
 * [!!] 与 reported_dest_up 分开存,因为它们回答不同的问题:
 *   up      —— dest 还在不在(在 = 每一项检查都绿)
 *   degraded —— dest 还在,但握手时延已经加在【每一条】用户新连接上
 * 一个上线时 40ms 的 dest 跑着跑着变成 400ms,up 全程是 true,
 * 而用户只会说"这节点变慢了" —— 那是最难归因的一种故障。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->unsignedInteger('reported_dest_latency_ms')->default(0)
                ->comment('节点上报的 dest 探测时延中位数(毫秒)');
            $t->boolean('reported_dest_degraded')->default(false)
                ->comment('dest 可达但明显变慢');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->dropColumn(['reported_dest_latency_ms', 'reported_dest_degraded']);
        });
    }
};
