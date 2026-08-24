<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 下次流量刷新时间：按开通日的月度周年计算，非日历1号
            $table->timestamp('next_reset_at')->nullable()->after('class_expire');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('next_reset_at');
        });
    }
};
