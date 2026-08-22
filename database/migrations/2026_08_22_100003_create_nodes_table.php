<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('server');                             // 连接地址(中转入口域名)
            $table->unsignedInteger('port');                     // 端口(多端口区分落地)
            $table->string('type')->default('vmess');            // 协议
            $table->string('net')->default('tcp');               // 传输 tcp/ws
            $table->decimal('traffic_rate', 5, 2)->default(1);   // 流量倍率
            $table->unsignedTinyInteger('node_class')->default(0); // 等级门槛(class>=此值可连)
            $table->unsignedInteger('node_group')->default(0);   // 分组
            $table->unsignedInteger('speed_limit')->default(0);  // 节点限速
            $table->string('secret', 64);                        // 节点通信密钥(一节点一个)
            $table->boolean('online')->default(true);
            $table->unsignedInteger('last_heartbeat')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->json('custom_config')->nullable();
            $table->timestamps();

            $table->index('node_class');
            $table->index('node_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
