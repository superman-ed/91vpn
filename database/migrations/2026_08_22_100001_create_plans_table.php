<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // VIP①
            $table->decimal('price', 12, 2)->default(0);         // 售价
            $table->string('period')->default('month');          // month/quarter/half_year/year
            $table->unsignedInteger('transfer_gb')->default(0);  // 给多少流量(GB)
            $table->unsignedTinyInteger('class')->default(0);    // 对应等级
            $table->unsignedInteger('speed_limit')->default(0);  // 限速 Mbps
            $table->unsignedInteger('ip_limit')->default(0);     // 设备数
            $table->unsignedInteger('duration_days')->default(30); // 时长(天)
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('on_sale')->default(true);
            $table->integer('stock')->default(-1);               // -1=无限
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
