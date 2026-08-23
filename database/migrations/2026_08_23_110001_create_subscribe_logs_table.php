<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribe_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->default('');
            $table->string('location')->default('');
            $table->string('client')->default('');   // 客户端UA
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribe_logs');
    }
};
