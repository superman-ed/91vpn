<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_no', 32)->nullable()->unique()->after('id');
        });

        // 回填历史订单：下单时间 + 补零 id,保证唯一
        Order::whereNull('order_no')->get()->each(function (Order $o) {
            $o->update(['order_no' => ($o->created_at ?: now())->format('YmdHis').str_pad((string) $o->id, 4, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_no');
        });
    }
};
