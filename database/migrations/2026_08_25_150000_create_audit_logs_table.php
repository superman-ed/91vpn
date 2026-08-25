<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete(); // 操作人(管理员)
            $table->string('action', 64);                // 动作 slug，如 user.update / order.mark_paid
            $table->string('description', 500)->default('');
            $table->string('target_type', 32)->nullable(); // 目标类型，如 user / order / node
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip', 45)->default('');
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
