<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 按转发规则的流量归因，按「规则 + 节点 + 天」累计。
 *
 * [!!] 带 node_id：同一条规则可能跑在多个节点上，合并统计就答不了
 * "哪台机被这条规则吃掉了多少"——而那正是要看的。
 *
 * [!] 下行是在【连接结束时】一次性结算的（中转下行走 splice，内核搬数据，
 * 只能在结束时问一次总数）。长连接的下行会滞后，所以这份数据用于
 * **归因**，不能当实时用量。实时用量看 node_net_traffic。
 * 详见 sogacore/docs/BUG-relay-downlink.md。
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
        Schema::create('rule_traffic', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('rule_id');
            $t->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $t->date('date');
            $t->unsignedBigInteger('up')->default(0);
            $t->unsignedBigInteger('down')->default(0);
            $t->timestamps();
            $t->unique(['rule_id', 'node_id', 'date']);
            $t->index('date');
        });
        // [!] rule_id 不设外键：规则被删掉之后，它已经产生的流量记录
        // 仍然有价值（"上个月是谁吃掉的"），级联删除会把账一起抹掉。
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_traffic');
    }
};
