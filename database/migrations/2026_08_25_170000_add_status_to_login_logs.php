<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_logs', function (Blueprint $table) {
            $table->string('status', 16)->default('success')->after('user_id'); // success / failed
            $table->string('email')->default('')->after('status');              // 失败时用户可能不存在，单独存尝试的邮箱
            $table->string('reason')->default('')->after('user_agent');         // 失败原因
            $table->index(['ip', 'status', 'logged_at']);
        });

        // user_id 允许为空（登录失败且邮箱不存在时无对应用户）
        Schema::table('login_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropIndex(['ip', 'status', 'logged_at']);
            $table->dropColumn(['status', 'email', 'reason']);
        });
    }
};
