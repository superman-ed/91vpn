<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 节点上报它【实际在跑】的协议。
 *
 * `[!!]` 协议不来自面板 —— sspanel/mod_mu 的契约是节点从本机 agent.conf 读
 * server_type（soga、XrayR 同样如此），下发的 nodeInfo 里没有这一项。
 * 于是在面板上把协议从 vmess 改成 vless，节点会【静默地继续跑 vmess】：
 * 它每轮 pull 都因 "REALITY requires protocol vless (got vmess)" 失败，
 * 而那只是节点自己日志里的一行 WARN —— 面板显示一切正常、心跳照常。
 *
 * `[D]` 实测踩中过，排查花了十四分钟。面板存下节点报的值，就能把
 * "你配的"和"它在跑的"摆在一起比。
 *
 * `[!]` nullable 且默认 null = "这台还没报过"（旧 agent 不带这个字段）。
 * 不要给默认值 —— 那会把"没报"伪装成"报了 vmess"。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->string('reported_server_type', 16)->nullable()->after('reported_accept_proxy');
            $t->timestamp('server_type_reported_at')->nullable()->after('reported_server_type');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->dropColumn(['reported_server_type', 'server_type_reported_at']);
        });
    }
};
