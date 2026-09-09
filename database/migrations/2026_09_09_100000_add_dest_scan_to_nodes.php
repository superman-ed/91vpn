<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REALITY dest 候选筛查（sogacore compatibility/dest-scan.md）。
 *
 * [!!] 筛查在【节点】上跑，不是面板：可达与延迟是"这台机器到那个站"的关系，
 * 而 REALITY 握手时是落地去连 dest。面板只负责下发候选、收结果、展示。
 *
 * [!] dest_scan_id 是幂等键。面板每个拉取周期都会重发同一份 nodeInfo，
 * 没有它节点会每 60 秒把同一批候选重扫一遍 —— 对第三方站点是持续的可疑流量。
 * 候选内容变了才换 id（内容哈希），"强制重扫"另加时间戳后缀。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->text('dest_scan_candidates')->nullable()->after('reality_short_ids'); // 换行/逗号分隔
            $t->string('dest_scan_id', 64)->nullable()->after('dest_scan_candidates');
            $t->json('dest_scan_result')->nullable()->after('dest_scan_id');           // 最近一轮结果
            $t->timestamp('dest_scan_at')->nullable()->after('dest_scan_result');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->dropColumn(['dest_scan_candidates', 'dest_scan_id', 'dest_scan_result', 'dest_scan_at']);
        });
    }
};
