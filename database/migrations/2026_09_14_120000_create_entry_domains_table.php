<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 入口域名池（复刻 SoCloud 的 cp.paeadiy.com 打法的 v1：纯登记 + 提醒）。
 *
 * 一个入口域名 = 面向客户端的稳定门牌，前置一台中转。订阅里发这个域名而非中转裸 IP；
 * IP 被墙就改这域名的 A 记录（本表只【记录 + 提醒】，不自己调 DNS API）。
 *
 * `[!]` 刻意从简（用户明确要求别复杂）：
 *   · 不存 DNS 服务商字段 —— 想记就写进 note；
 *   · 不做轮换历史表 —— 轮换事件进现有 audit 日志（entry_domain.rotate）；
 *   · 不碰真实 DNS、不做地理分流/自动探测 —— 那是以后升级到半自动/全自动才长的东西，
 *     表结构已给它们留好位（加列即可，不必重建）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_domains', function (Blueprint $t) {
            $t->id();
            $t->string('domain', 253)->unique();          // 客户端连的入口域名
            // 前置哪台中转。中转删了，它的入口域名一并清掉（域名脱离了中转没有意义）。
            $t->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            // active=订阅发它（一台中转至多一个）; standby=备用已配好可切; blocked=被墙待轮换
            $t->string('status', 16)->default('standby');
            // 这域名 A 记录【你上次设成】的 IP —— 和中转真 IP 比对即得"该改 DNS 了"。
            $t->string('pointed_ip', 45)->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamp('last_rotated_at')->nullable();
            $t->timestamps();
            $t->index(['node_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_domains');
    }
};
