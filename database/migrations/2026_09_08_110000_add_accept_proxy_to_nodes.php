<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 落地节点是否接受中转发来的 PROXY protocol 头(B-2:落地在 dokodemo 透明中转后面时,
 * 靠 PROXY 头拿到真实客户端 IP,否则源 IP 全塌缩成中转 IP → 限设备/在线数失效)。
 *
 * ②b 已实测(compatibility/b2-reality-through-relay.md):剥头在 REALITY 之前完成,
 * 中转发 v2 + 落地 accept 即通,v1/v2 不必两端对齐 → 落地侧只需一个 bool 开关。
 *
 * [!] 安全前提(运维层,非本字段):开了 accept 必须防火墙只放行中转源 IP,
 *     否则 PROXY 头无认证、源 IP 可被任意伪造。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('accept_proxy_protocol')->default(false)->after('reality_short_ids');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('accept_proxy_protocol');
        });
    }
};
