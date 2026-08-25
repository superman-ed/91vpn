<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to_email');
            $table->string('type', 32)->default('');       // 邮件类型：code(验证码) / notice ...
            $table->string('subject')->default('');
            $table->string('status', 16)->default('sent');  // sent 成功 / failed 失败 / logged 未配SMTP仅记录
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('to_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
