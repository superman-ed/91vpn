<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 加油包剩余额度(字节)：跨月保留、随套餐结束作废，消耗顺序在基础配额之后
            $table->unsignedBigInteger('pack_transfer')->default(0)->after('base_transfer_enable');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pack_transfer');
        });
    }
};
