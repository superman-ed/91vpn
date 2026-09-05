<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 入站的传输/安全层参数单独成列。
 *
 * [!] 起初把 ws/grpc/reality 这些块塞进了 inbound_cred 的 `_extra` 里 ——
 * 一测就看出不对：编译时它会原样出现在下发 JSON 的 `credential` 字段里，
 * 而 credential 在 agent 侧是【凭据】（uuid/password/cipher/username），
 * 多出来的键要么被忽略、要么被当成配置错误。
 *
 * 更根本的问题是语义混淆：REALITY 的私钥确实是秘密，但 dest / server_names
 * 不是；把它们和用户凭据混在一列里，将来做"凭据轮换"时会连传输参数一起换掉。
 *
 * 故拆成独立的一列 inbound_opts，编译时展开成 ws/grpc/reality 三个平级块。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forward_rules', function (Blueprint $table) {
            $table->json('inbound_opts')->nullable()->after('inbound_cred');
        });
    }

    public function down(): void
    {
        Schema::table('forward_rules', function (Blueprint $table) {
            $table->dropColumn('inbound_opts');
        });
    }
};
