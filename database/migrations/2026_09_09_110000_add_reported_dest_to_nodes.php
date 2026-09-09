<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 节点回报的 REALITY dest 探活（sogacore bfd8740）。
 *
 * [!!] dest 失效是【静默】的:借用的第三方站点某天挂掉、或被套上 CDN,
 * 节点照常启动、端口照常监听、面板一切正常,而没有任何客户端能完成握手。
 * agent 一直在探(30 秒缓存 + 连续失败计数),此前只写日志 ——
 * 而看日志的前提是有人知道该去看。
 *
 * [!] reported_dest_up 用 nullable boolean 表三态:
 * null = 从没报过(非 reality 节点,或 agent 版本旧),不是"好的"。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->string('reported_dest', 255)->nullable()->after('accept_proxy_reported_at');
            $t->boolean('reported_dest_up')->nullable()->after('reported_dest');
            $t->unsignedInteger('reported_dest_failures')->default(0)->after('reported_dest_up');
            $t->timestamp('dest_reported_at')->nullable()->after('reported_dest_failures');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->dropColumn(['reported_dest', 'reported_dest_up', 'reported_dest_failures', 'dest_reported_at']);
        });
    }
};
