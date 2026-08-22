<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('type')->default('percent');       // percent(百分比折扣) / amount(固定减)
            $table->decimal('value', 12, 2);                  // percent: 0-100; amount: 元
            $table->integer('max_use')->default(-1);          // -1=无限
            $table->integer('used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
