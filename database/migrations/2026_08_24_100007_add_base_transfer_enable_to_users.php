<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 套餐基础配额(字节)：月度重置时 transfer_enable 恢复到此值，抹掉上周期加油包
            $table->unsignedBigInteger('base_transfer_enable')->default(0)->after('transfer_enable');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('base_transfer_enable');
        });
    }
};
