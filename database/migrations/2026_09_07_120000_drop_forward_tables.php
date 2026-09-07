<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 移除转发规则的两张表。
 *
 * [!] 中转拓扑改由独立的中转面板（relaypanel）管理 —— 它有自己的库、
 * 自己的下发端点，节点直接连它。91vpn 这边保留这两张表只会带来一个问题：
 * 两处都能改中转配置，而只有一处会被下发，迟早有人在错的地方改。
 *
 * [!] 执行前确认过是空表（0 规则 / 0 出站 / 0 中转角色节点）。
 *
 * [!] nodes.role 列【保留】：91vpn 仍用它判断要不要给这个节点发用户名单
 * （D-1，见 ModMu\UserController::index）。删列是破坏性的，收益也不成立。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('forward_outbounds'); // 先删有外键的一侧
        Schema::dropIfExists('forward_rules');
    }

    public function down(): void
    {
        // [!] 不提供回滚：这两张表的建表语句还在早先的迁移里，
        // 真要回去应当回滚到那几个迁移，而不是在这里复制一份定义 ——
        // 复制出来的定义迟早和原始版本不一致。
        throw new \RuntimeException(
            '不支持回滚：中转拓扑已迁至 relaypanel，如需恢复请回滚 2026_09_06_1000* 那几个迁移'
        );
    }
};
