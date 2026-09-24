<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `[!!]` nodes.online 的默认值从 true 改成 false。
 *
 * 原来的默认值是 true,而后台的节点表单【没有 online 字段】—— 于是
 * 在后台点一下「新增节点」,新节点就带着 online=1、enabled=1、
 * last_heartbeat=0 落库。而订阅筛的是 `online=1 AND enabled=1`,
 * 所以它【立刻进入所有人的订阅】,声称在线而从未上报过。
 *
 * `[D]` 2026-09-24 实际发生:19 个 server 指向 .placeholder.invalid 的占位
 * 节点进了订阅(v2rayNG 拿到 20 个节点,19 个连不上),还把落地页的
 * 「通航地区」从 1 个吹到 13 个 —— 因为地区是从启用节点的名字里识别的。
 *
 * 改成 false 之后 online 这个字段就诚实了:
 *   建节点            → online=0,不进订阅(哪怕 enabled=1)
 *   agent 首次上报    → ModMu\UserController 置 online=1 → 进订阅
 *   agent 崩/停 180s  → nodes:mark-offline 置 online=0 → 出订阅
 *
 * `[!]` 刻意【不动】MarkNodesOffline 对 last_heartbeat=0 的排除。
 * 那条排除是有意的(见该命令的注释),而这次的根因是默认值,不是它。
 * 后台也没有把 online 置真的入口,所以没必要去翻那个决定。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('online')->default(false)->change();
        });

        // 把已有数据也修成诚实的:从未上报过的节点不该声称在线。
        // `[!]` 只碰 last_heartbeat=0 的行 —— 有过心跳的节点由
        //   心跳与 nodes:mark-offline 共同维护,这里不插手。
        DB::table('nodes')->where('last_heartbeat', 0)->update(['online' => false]);
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('online')->default(true)->change();
        });
        // `[!]` 不回填 online=1：无法知道每行原本是什么,乱写比不写更糟。
    }
};
