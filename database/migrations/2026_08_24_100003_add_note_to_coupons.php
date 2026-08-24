<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('note')->nullable()->after('code');        // 收银台展示文案，如“VIP ①②③ 半年套餐 95 折优惠码”
            $table->boolean('show_on_checkout')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['note', 'show_on_checkout']);
        });
    }
};
