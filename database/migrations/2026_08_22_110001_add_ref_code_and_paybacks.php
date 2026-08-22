<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ref_code', 16)->nullable()->unique()->after('ref_by');
        });

        Schema::create('paybacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();      // 受益人(邀请人)
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete(); // 下线
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);                                     // 返利金额
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paybacks');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('ref_code'));
    }
};
