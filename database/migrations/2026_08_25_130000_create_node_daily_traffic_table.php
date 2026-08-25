<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_daily_traffic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('u')->default(0);       // 原始上行(服务器真实带宽)
            $table->unsignedBigInteger('d')->default(0);       // 原始下行
            $table->unsignedBigInteger('billed')->default(0);  // 计费流量合计(原始×倍率)
            $table->timestamps();

            $table->unique(['node_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_daily_traffic');
    }
};
