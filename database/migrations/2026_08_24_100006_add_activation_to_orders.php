<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // status 新增 'queued'：已支付但当前套餐未到期，排队等待激活
            $table->timestamp('activate_at')->nullable()->after('paid_at');   // 预计生效时间
            $table->timestamp('delivered_at')->nullable()->after('activate_at'); // 实际发货时间
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['activate_at', 'delivered_at']);
        });
    }
};
