<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 转发规则。
 *
 * [!!] 字段与 sogacore/docs/RELAY-SCHEMA.md §2 一一对应 —— 那份 schema 是
 * 三边（本表 / 下发 JSON / agent 的 Go 结构体）冻结过的共识，不要在这里
 * 自行增删字段名。列的形状与 91vpn 那份保持一致，是为了 ForwardRuleService
 * 能原样搬过来、两边的编译结果可以逐字节对比。
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
        Schema::create('forward_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('enabled')->default(true)->index();
            $t->unsignedInteger('speed_limit')->default(0);

            // 入站：本规则在哪些节点上监听什么
            $t->string('inbound_type', 16)->default('direct');
            $t->json('inbound_node_set')->nullable();
            $t->boolean('listen_all_nics')->default(true);
            $t->string('listen_nic_ip')->nullable();
            $t->boolean('port_is_range')->default(false);
            $t->string('listen_port', 32)->default('');
            $t->boolean('mptcp')->default(false);
            $t->string('inbound_transport', 8)->nullable();
            $t->string('inbound_security', 8)->nullable();
            $t->boolean('accept_proxy_protocol')->default(false);
            $t->json('inbound_cred')->nullable();
            $t->json('inbound_opts')->nullable();

            // 均衡与健康检查
            $t->string('balance', 16)->default('roundrobin');
            $t->string('backup_balance', 16)->default('fallback');
            $t->boolean('hc_enabled')->default(true);
            $t->unsignedInteger('hc_interval_sec')->default(10);
            $t->unsignedInteger('hc_max_fail')->default(3);
            $t->unsignedInteger('hc_max_success')->default(2);

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_rules');
    }
};
