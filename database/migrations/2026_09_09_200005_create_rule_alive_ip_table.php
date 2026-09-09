<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 转发规则的在线来源 IP。
 *
 * [decided] D-1：中转不认证用户，所以这里【只有 IP，没有 user_id】——
 * 它回答的是"有多少个不同的来源在用这条中转"，不是"谁在用"。
 * 想知道"谁"，那是落地节点的事，在 91vpn 里。
 *
 * [!!] 这是【当前状态】不是流水：同一个 (规则, 节点, IP) 只有一行，
 * 反复上报只更新 last_seen。存成流水的话，一个挂了一天的连接会产生
 * 一千多行，而它表达的还是同一件事。
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
        Schema::create('rule_alive_ip', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('rule_id');
            $t->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $t->string('ip', 45);          // 45 = IPv6 最长形态
            $t->timestamp('last_seen');
            $t->timestamps();
            $t->unique(['rule_id', 'node_id', 'ip']);
            $t->index('last_seen');        // 清理过期用
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_alive_ip');
    }
};
