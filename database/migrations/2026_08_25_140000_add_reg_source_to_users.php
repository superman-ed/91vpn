<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('reg_ip', 45)->nullable()->after('ref_by');       // 注册来源 IP
            $table->string('reg_referer', 500)->nullable()->after('reg_ip'); // 注册来路(HTTP referer)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reg_ip', 'reg_referer']);
        });
    }
};
