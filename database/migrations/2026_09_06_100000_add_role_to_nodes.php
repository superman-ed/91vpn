<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给节点加「角色」。
 *
 * 角色决定这个节点在中转舰队里干什么，与 node_group 是两回事 ——
 * 分组是 UI 上的归类，角色是语义（见 sogacore 的 docs/RELAY-SCHEMA.md §1）。
 *
 *   landing      落地：认证用户、计量流量、限设备数（现有节点全是这个）
 *   relay        中转：只透传字节，不认证、不持用户名单
 *   springboard  跳板：链路中间跳，同 relay
 *   front        入口：用户直连的那一跳，同 relay
 *   both         既是落地又承担中转
 *
 * [!] 默认 landing —— 现有节点行为完全不变。
 *
 * [!] 只有 relay/springboard/front/both 会拿到转发规则；landing 请求
 *     /mod_mu/nodes/{id}/routes 会得到 404，agent 据此判定"本节点无中转",
 *     记一条 info 后停止轮询（不会反复重试刷日志）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('role', 16)->default('landing')->after('enabled');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
