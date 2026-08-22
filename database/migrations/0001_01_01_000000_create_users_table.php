<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // 连接凭证（连 VPN 用，区别于登录密码）
            $table->uuid('uuid');                              // VMess id
            $table->string('passwd', 64);                     // 连接密码（改则同时换 uuid）
            // 流量（字节）
            $table->unsignedBigInteger('u')->default(0);      // 已用上传
            $table->unsignedBigInteger('d')->default(0);      // 已用下载
            $table->unsignedBigInteger('transfer_enable')->default(0); // 流量配额
            $table->unsignedBigInteger('transfer_today')->default(0);  // 今日已用
            // 等级与时长
            $table->unsignedTinyInteger('class')->default(0); // 0=普通,1/2/3=VIP①②③
            $table->dateTime('class_expire')->nullable();     // 等级到期
            // 限制
            $table->unsignedInteger('node_speed_limit')->default(0); // 限速 Mbps，0=不限
            $table->unsignedInteger('node_ip_limit')->default(0);    // 设备数上限
            // 钱与邀请
            $table->decimal('money', 12, 2)->default(0);      // 余额
            $table->unsignedBigInteger('ref_by')->nullable(); // 邀请人 id
            // token
            $table->string('invite_token', 32)->unique();     // 订阅链接 token
            $table->string('api_token', 60)->nullable();      // 客户端 API token
            // 状态
            $table->boolean('is_admin')->default(false);
            $table->boolean('banned')->default(false);
            $table->unsignedInteger('last_check_in')->default(0); // 最后签到时间戳
            $table->rememberToken();
            $table->timestamps();

            $table->index('class');
            $table->index('ref_by');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
