<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();              // 统计日
            $table->unsignedInteger('dau')->default(0);          // 当日活跃用户(用过流量)
            $table->unsignedInteger('peak_online')->default(0);  // 当日在线峰值(去重用户)
            $table->unsignedInteger('new_users')->default(0);    // 当日新增注册
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
