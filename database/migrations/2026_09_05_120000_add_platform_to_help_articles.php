<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 帮助文档按平台区分:all=通用 / android / windows / ios / macos。
// 客户端请求 /api/help?platform=windows 时返回 all + windows;不传 platform 则全返(向后兼容旧端)。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->string('platform', 16)->default('all')->index()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
