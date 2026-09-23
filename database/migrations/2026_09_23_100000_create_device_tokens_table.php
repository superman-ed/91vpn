<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 按设备发认证 token(Level A:下线=强制登出)。与 devices(限额+机型)解耦:
// 登录带 device_id 时发/取该设备的 token;下线时删除对应 token → 该设备下次调 API 401 → 重登。
// 不带 device_id 的登录(旧端/网页)仍回退账号级 users.api_token(向后兼容)。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('device_id', 128);
            $t->string('token', 80)->unique();   // unique 已建索引,登录/鉴权按 token 查
            $t->timestamps();
            $t->unique(['user_id', 'device_id']); // 每账号每设备一条
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
