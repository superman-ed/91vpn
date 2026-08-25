<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('utm_source', 64)->nullable()->after('reg_referer');   // 推广来源 telegram / google ...
            $table->string('utm_medium', 64)->nullable()->after('utm_source');    // 媒介 cpc / social / banner
            $table->string('utm_campaign', 128)->nullable()->after('utm_medium'); // 活动 spring2026 ...
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
