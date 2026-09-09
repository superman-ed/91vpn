<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 出站上游。见 RELAY-SCHEMA §2.2。 */
/**
 * [!] 本文件由 relaypanel 原样搬入（ADR-008：中转面板并回 91vpn）。
 * 注释保持原样 —— 那是三边（本表 / 下发 JSON / agent 的 Go 结构体）
 * 冻结过的共识，不要在这里自行增删字段名。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forward_outbounds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id')->constrained('forward_rules')->cascadeOnDelete();
            $t->string('pool', 8)->default('primary');   // primary | backup
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('enabled')->default(true);
            $t->unsignedInteger('weight')->default(0);

            $t->string('out_type', 16)->default('direct');
            // out_type=relay_rule 时指向被引用的规则 —— 多跳的组合原语。
            // 编译期解析成被引用规则的【入站地址】，agent 不认识引用。
            $t->unsignedBigInteger('relay_rule_ref')->nullable();
            $t->json('target_node_set')->nullable();
            $t->string('target_addr')->nullable();
            $t->string('target_port', 32)->nullable();
            $t->string('out_transport', 8)->nullable();
            $t->string('out_security', 8)->nullable();
            $t->string('fingerprint', 32)->nullable();
            $t->boolean('out_mptcp')->default(false);
            $t->string('sni')->nullable();
            $t->unsignedTinyInteger('send_proxy_protocol')->default(0);
            $t->boolean('source_in_source_out')->default(false);
            // [!!] 抗封约束 A：不为真时，这一跳【必须】伪装（tls/reality），
            // 否则 agent 拒绝整条规则。
            $t->boolean('trusted_transit')->default(false);
            $t->json('out_cred')->nullable();
            // [!!] 传输/安全层的附加参数：reality 的 public_key/short_id、
            // ws 的 path/host、grpc 的 service_name、mux 的开关。
            //
            // 起初只有入站有 inbound_opts，出站什么都不带 —— 结果是面板
            // 【永远配不出一个合法的 reality 出站】（agent 校验要求
            // reality.public_key），而这恰恰是实测跑通过的那条链路。
            // 漏掉它等于面板表达不了 agent 支持的东西。
            $t->json('out_opts')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_outbounds');
    }
};
