<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recharges', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');   // pending/paid
            $table->string('trade_no', 64)->nullable();     // 网关交易号
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::table('balance_logs', function (Blueprint $table) {
            $table->string('trade_no', 64)->nullable()->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharges');
        Schema::table('balance_logs', fn (Blueprint $table) => $table->dropColumn('trade_no'));
    }
};
