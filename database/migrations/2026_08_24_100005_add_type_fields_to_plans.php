<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // monthly=每30天从购买日重置；none=总量型不重置(如轻量套餐)
            $table->string('reset_type')->default('monthly')->after('transfer_gb');
            // 流量包(加油包)：支付后立即给当前周期加流量，不改等级/到期、不排队
            $table->boolean('is_data_pack')->default(false)->after('reset_type');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['reset_type', 'is_data_pack']);
        });
    }
};
