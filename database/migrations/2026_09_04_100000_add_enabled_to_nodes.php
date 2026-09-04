<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 拆分节点两个语义:online(心跳驱动=agent 活着)vs enabled(运维手动=是否对用户开放)。
// 解决"心跳无条件写 online=true 导致无法排空节点"的运维死结:排空时置 enabled=false,
// agent 照常在线、心跳照写 online,但节点立即从订阅/可服务名单摘除,漏干后再停 agent。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('online'); // 是否对用户开放(运维手动,心跳不碰)
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
