<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_logs', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('balance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
