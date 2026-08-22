<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('u')->default(0);         // 本次上传(字节)
            $table->unsignedBigInteger('d')->default(0);         // 本次下载(字节)
            $table->decimal('rate', 5, 2)->default(1);           // 结算倍率
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_logs');
    }
};
