<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('pv')->default(0)->after('enabled'); // 总点击
            $table->unsignedBigInteger('uv')->default(0)->after('pv');      // 去重访客(session)
        });
    }

    public function down(): void
    {
        Schema::table('promo_channels', function (Blueprint $table) {
            $table->dropColumn(['pv', 'uv']);
        });
    }
};
