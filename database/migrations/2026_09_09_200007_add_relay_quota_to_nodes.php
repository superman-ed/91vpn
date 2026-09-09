<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 节点的月流量额度与用量。
 *
 * [!!] 这里存的是【整机网卡】用量，不是代理流量 —— 系统更新、备份、
 * 其它服务都算在内。理由：中转节点没有"按用户计量"可言（D-1：中转不
 * 认证用户），而按规则计量目前下行恒为 0（见 agent 的 ForwardTraffic）。
 * 在那个缺口补上之前，整机用量是中转唯一可靠的额度依据 ——
 * 而且机房本来也是按整机算的，用它做额度告警反而更贴近实际账单。
 *
 * [!] 不要拿它计费。
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
        Schema::table('nodes', function (Blueprint $t) {
            // 0 = 不限。单位 GB，与机房的说法一致。
            $t->unsignedInteger('quota_gb')->default(0);
            // 计费周期起始日（每月几号）。机房的周期起点各不相同。
            $t->unsignedTinyInteger('quota_reset_day')->default(1);
        });

        Schema::create('node_net_traffic', function (Blueprint $t) {
            $t->id();
            $t->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $t->date('date');
            $t->unsignedBigInteger('up')->default(0);
            $t->unsignedBigInteger('down')->default(0);
            $t->timestamps();
            $t->unique(['node_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_net_traffic');
        Schema::table('nodes', function (Blueprint $t) {
            $t->dropColumn(['quota_gb', 'quota_reset_day']);
        });
    }
};
