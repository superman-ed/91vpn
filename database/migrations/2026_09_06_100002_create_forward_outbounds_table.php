<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 转发规则的出站（1:N，含主/备两池）。
 *
 * 字段与 sogacore 的 docs/RELAY-SCHEMA.md §2.2 对齐。
 *
 * [!] 两级池：pool=primary 是主池，backup 是兜底。主池【全部】判死才启用备池 ——
 *     不是"主池不够就补备池"。混着用会让备池长期承压，等真正需要兜底时
 *     它也已经不堪用。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forward_outbounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('forward_rules')->cascadeOnDelete();
            $table->string('pool', 8)->default('primary'); // primary|backup
            $table->unsignedInteger('sort')->default(0);   // 池内顺序
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('weight')->default(0);

            // relay_rule = 引用另一条规则（多跳的组合原语）；其余是具体出站。
            $table->string('out_type', 16)->default('direct');
            // [!] 引用【必须在编译期解析掉】—— 下发给 agent 的只能是具体地址。
            $table->unsignedBigInteger('relay_rule_ref')->nullable();

            $table->json('target_node_set')->nullable();  // 落地节点 id 集
            $table->string('target_addr')->nullable();    // 或直接给地址
            $table->string('target_port', 32)->nullable();
            $table->string('out_transport', 8)->nullable();
            $table->string('out_security', 8)->nullable();
            $table->string('fingerprint', 32)->nullable(); // uTLS 指纹（抗封）
            $table->boolean('out_mptcp')->default(false);
            $table->string('sni')->nullable();
            $table->unsignedTinyInteger('send_proxy_protocol')->default(0); // 0|1|2
            // [decided] D-3：语义未实证，保留占位不实现。agent 侧置 true 会报错。
            $table->boolean('source_in_source_out')->default(false);
            // [!!] 抗封约束 A：过墙那跳默认必须伪装。
            // false（默认）且 out_security 不是 tls/reality 时，agent 会【拒绝】
            // 这条规则 —— 要裸奔就得显式声明这跳走专线/内网。
            $table->boolean('trusted_transit')->default(false);
            $table->json('out_cred')->nullable();

            // 代理模式（经另一台机器出网）
            $table->boolean('proxy_mode')->default(false);
            $table->unsignedBigInteger('proxy_node_id')->nullable();
            $table->string('proxy_addr')->nullable();
            $table->unsignedInteger('proxy_port')->nullable();

            $table->timestamps();
            $table->index(['rule_id', 'pool', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_outbounds');
    }
};
