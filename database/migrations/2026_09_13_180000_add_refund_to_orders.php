<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 订单退款。
 *
 * `[!!]` 只记录【钱】这一侧，不记录"权益已回滚"—— 因为回滚做不到。
 * deliver() 是覆盖写：transfer_enable / class / class_expire 直接被新值盖掉，
 * u/d 清零，而【覆盖前的值没有任何地方留存】。
 * 所以"退款自动撤销权益"要么猜一个旧值，要么把用户打回零 —— 两种都是错的。
 * 诚实的做法是：钱退准确，权益让人显式决定，并在界面上说清楚为什么。
 *
 * `[!]` refund_amount 单独存而不是复用 amount：允许部分退款，
 * 而且"退了多少"与"当初收了多少"是两个事实，对账时都要。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('refunded_at')->nullable()->after('delivered_at');
            $t->decimal('refund_amount', 12, 2)->nullable()->after('refunded_at');
            $t->string('refund_reason', 255)->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['refunded_at', 'refund_amount', 'refund_reason']);
        });
    }
};
