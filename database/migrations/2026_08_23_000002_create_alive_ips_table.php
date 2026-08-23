<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alive_ips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('node_id')->nullable();  // 最后上报该 IP 的节点
            $table->string('ip', 45);
            $table->dateTime('last_seen')->index();
            $table->timestamps();

            $table->unique(['user_id', 'ip']);  // 同一用户同一 IP 只算一台设备
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alive_ips');
    }
};
