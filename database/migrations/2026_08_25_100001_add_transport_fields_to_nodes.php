<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('host')->default('')->after('net');   // ws host / TLS SNI
            $table->string('path')->default('')->after('host');  // ws 路径
            $table->boolean('tls')->default(false)->after('path'); // 是否启用 TLS
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['host', 'path', 'tls']);
        });
    }
};
