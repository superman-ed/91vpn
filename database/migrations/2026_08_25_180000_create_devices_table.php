<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 128);           // 客户端生成的唯一设备标识
            $table->string('platform', 16)->default(''); // ios / android / windows / macos / linux
            $table->string('brand', 64)->default('');    // 厂商：Xiaomi / Samsung / Apple ...
            $table->string('model', 128)->default('');   // 机型：Redmi K60 / iPhone15,3 ...
            $table->string('os_version', 32)->default(''); // 14 / 17.2
            $table->string('app_version', 32)->default(''); // 自研客户端版本
            $table->string('ip', 45)->default('');
            $table->dateTime('last_seen')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index('platform');
            $table->index('last_seen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
