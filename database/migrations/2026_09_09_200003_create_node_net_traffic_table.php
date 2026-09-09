<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 中转机按天的流量。
 *
 * [!] 只到"节点 + 天"这个粒度。按规则计量 agent 还没上报（见
 * sogacore/docs/ROUND-2026-09.md 的监控缺口），有了再加列或加表，
 * 不预先造一张填不满的表。
 */
/**
 * [!] 本文件由 relaypanel 原样搬入（ADR-008：中转面板并回 91vpn）。
 * 注释保持原样 —— 那是三边（本表 / 下发 JSON / agent 的 Go 结构体）
 * 冻结过的共识，不要在这里自行增删字段名。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_traffic', function (Blueprint $t) {
            $t->id();
            $t->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $t->date('date');
            $t->unsignedBigInteger('u')->default(0);
            $t->unsignedBigInteger('d')->default(0);
            $t->timestamps();
            $t->unique(['node_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_traffic');
    }
};
