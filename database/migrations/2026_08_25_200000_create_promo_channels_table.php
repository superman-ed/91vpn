<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();   // 推广码，注册链接 ?ch=CODE
            $table->string('name');                 // 代理/渠道名称
            $table->string('note', 500)->default(''); // 备注（联系方式/结算约定等，仅内部可见）
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('promo_code', 64)->nullable()->after('utm_campaign'); // 注册时来源推广码
            $table->index('promo_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['promo_code']);
            $table->dropColumn('promo_code');
        });
        Schema::dropIfExists('promo_channels');
    }
};
