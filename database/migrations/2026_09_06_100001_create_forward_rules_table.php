<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 转发规则（舰队模型）。
 *
 * 字段与 sogacore 的 docs/RELAY-SCHEMA.md §2.1 对齐 —— 那份 schema 是三边
 * （本表 / 下发 JSON / agent Go 结构体）冻结过的共识，改这里要同步改那边。
 *
 * [!] 这张表存的是【舰队视角】：入站可以挂在一组节点上、出站可以引用另一条
 *     规则。下发给 agent 之前要在服务层【编译】成具体的"我监听 X、拨号到 Y"——
 *     agent 不认识"节点集/分组/引用"这些概念（SCHEMA §0）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forward_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            // link=链路转发（自己承担入站），springboard=跳板转发（引用一条共享规则）
            $table->string('method', 16)->default('link');
            $table->unsignedBigInteger('node_group_id')->nullable();
            $table->unsignedInteger('speed_limit')->default(0); // Mbps，0=不限

            // ---- 入站（1:1 内联）----
            $table->string('inbound_type', 16)->default('direct');
            // 运行此入站的节点 id 集。编译时按节点切片：每个节点只拿到自己那份。
            $table->json('inbound_node_set')->nullable();
            $table->boolean('listen_all_nics')->default(true);
            $table->string('listen_nic_ip')->nullable();
            $table->boolean('port_is_range')->default(false);
            $table->string('listen_port', 32)->default(''); // "10001" 或 "10100-10110"
            $table->boolean('mptcp')->default(false);
            $table->string('inbound_transport', 8)->nullable();  // tcp|ws|grpc
            $table->string('inbound_security', 8)->nullable();   // none|tls|reality
            $table->boolean('accept_proxy_protocol')->default(false);
            $table->unsignedBigInteger('inbound_cert_id')->nullable();
            // [decided] D-2：节点间凭据由面板铸造，不是用户凭据。
            // 中转类节点不认证用户（D-1），这份凭据只用于节点之间。
            $table->json('inbound_cred')->nullable();
            // 跳板：被其它规则的 relay_rule_ref 引用（SCHEMA §2.3）
            $table->boolean('is_shared')->default(false);

            // ---- 均衡与健康检查 ----
            $table->string('balance', 16)->default('roundrobin');
            $table->string('backup_balance', 16)->default('fallback');
            $table->boolean('hc_enabled')->default(true);
            // [decided] D-4：默认 10 秒、可配、下限 3。
            $table->unsignedInteger('hc_interval_sec')->default(10);
            $table->unsignedInteger('hc_max_fail')->default(3);
            $table->unsignedInteger('hc_max_success')->default(2);

            $table->timestamps();
            $table->index('enabled');
            $table->index('is_shared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_rules');
    }
};
