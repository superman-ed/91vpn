<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给节点加 REALITY + flow 字段(B-1:让 SSPanel 落地能做「REALITY 伪装 + 用户级认证」入站)。
 *
 * 设计口径(与 sogacore docs/RELAY-SCHEMA、relaypanel 一致):
 * - "是否 reality" 用 reality_private_key 是否非空来判(不另立 security 列,避免和既有 tls 冗余/漂移);
 *   下发/订阅时 security 由此派生:private_key 有=reality,否则 tls?tls:none。
 * - private_key【只下发给 agent(nodeInfo),绝不进客户端订阅】;订阅只出 public_key 等公开子集。
 * - flow = vless 的 xtls-rprx-vision(可与 reality 或 tls 搭配)。
 * - 全部 nullable、无默认 → 现有 vmess 节点行为完全不变。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('flow', 32)->nullable()->after('tls');                 // xtls-rprx-vision
            $table->string('reality_dest', 255)->nullable()->after('flow');       // 借用的真站,如 www.apple.com:443
            $table->json('reality_server_names')->nullable()->after('reality_dest'); // SNI 白名单
            $table->text('reality_private_key')->nullable()->after('reality_server_names'); // 仅 agent,勿进订阅
            $table->string('reality_public_key', 128)->nullable()->after('reality_private_key'); // 进订阅(公开)
            $table->json('reality_short_ids')->nullable()->after('reality_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['flow', 'reality_dest', 'reality_server_names',
                'reality_private_key', 'reality_public_key', 'reality_short_ids']);
        });
    }
};
