<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribe_logs', function (Blueprint $table) {
            // 订阅格式类型（clash/v2ray/sub/base64/config…），拉取时按 flag 记录
            $table->string('type', 32)->default('')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscribe_logs', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
