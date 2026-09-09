<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 转发规则各上游的当前状态。
 *
 * [!!] 这是【当前状态】不是历史：同一个 (规则, 节点, tag) 只有一行，
 * 每次上报覆盖。要看历史趋势那是另一件事（会需要时序存储），
 * 而这一页要回答的是"现在哪个落地挂了"。
 *
 * [!] 存 dial 而不是靠 tag 序号反推面板自己的配置：热更新途中、
 * 或者面板改了而节点还没拉到时，【节点实际在拨的】和【面板以为它在拨的】
 * 不是一回事 —— 而那恰恰是出问题时最需要看清的。
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
        Schema::create('rule_outbound_status', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('rule_id');
            $t->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $t->string('tag', 64);
            $t->string('dial')->nullable();
            $t->boolean('backup')->default(false);
            $t->boolean('alive')->default(true);
            $t->unsignedInteger('live')->default(0);
            $t->timestamp('reported_at');
            $t->timestamps();
            $t->unique(['rule_id', 'node_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_outbound_status');
    }
};
