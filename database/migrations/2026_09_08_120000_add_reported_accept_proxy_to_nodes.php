<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 落地节点【实际生效】的 accept_proxy(节点状态上报里带,eaca9fe)——面板存下它,
 * 用于配对校验:转发规则出站配了 send_proxy>0 → 目标落地必须报 accept_proxy=true,否则标红。
 *
 * [!] 与 accept_proxy_protocol(面板配的期望值)分开存:
 *     accept_proxy_protocol = 面板想要的;reported_accept_proxy = 节点真在跑的。
 *     两者不一致本身就是要暴露的问题(节点没升级/回落本地配)。
 * [!] nullable:null = 该节点从没上报过(可能旧版本 agent),界面据此区分"关着"与"不会报"。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('reported_accept_proxy')->nullable()->after('accept_proxy_protocol');
            $table->timestamp('accept_proxy_reported_at')->nullable()->after('reported_accept_proxy');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['reported_accept_proxy', 'accept_proxy_reported_at']);
        });
    }
};
