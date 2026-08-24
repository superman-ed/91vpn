<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 加油包改为“重置日/到期日清零”，不再跨周期保留，故不需要单独的剩余额度字段
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pack_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('pack_transfer')->default(0)->after('base_transfer_enable');
        });
    }
};
