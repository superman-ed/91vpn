<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理员角色。
 *
 * `[!!]` 与 `is_admin` 【分工明确,不是替代】:
 *     is_admin     能不能【进】后台
 *     admin_role   进来之后能【做什么】
 * 不合并的理由:is_admin 散在 30 多处(含工单回复的同名列),
 * 合并等于一次性改动一大片本可以不动的代码,而收益只是少一个字段。
 *
 * `[!!]` 迁移时把现有管理员全部置为 super —— 不能让升级本身变成一次降权。
 * 宁可先保持原样,再由人显式收窄,也不要让某个管理员第二天发现自己进不去了
 * 而不知道为什么。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('admin_role', 16)->nullable()->after('is_admin');
        });
        // `[!]` 只动 is_admin 为真的行。普通用户的 admin_role 保持 null ——
        // null 的含义是"不是管理员",与"是管理员但没角色"必须能区分开。
        \DB::table('users')->where('is_admin', true)->update(['admin_role' => 'super']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('admin_role');
        });
    }
};
