<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 自研客户端 JS 层崩溃/未捕获错误上报(自建,替代第三方 Sentry)。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // 游客崩溃时为空
            $table->string('device_id', 128)->default('');
            $table->string('platform', 16)->default('');
            $table->string('brand', 64)->default('');
            $table->string('model', 128)->default('');
            $table->string('os_version', 32)->default('');
            $table->string('app_version', 32)->default('');
            $table->string('message', 500)->default('');   // 错误摘要(截断)
            $table->text('stack')->nullable();              // JS 堆栈 + 组件栈
            $table->string('fingerprint', 40)->default(''); // message 归一化后的哈希,用于聚合
            $table->string('ip', 45)->default('');
            $table->timestamps();

            $table->index('fingerprint');
            $table->index('app_version');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_logs');
    }
};
