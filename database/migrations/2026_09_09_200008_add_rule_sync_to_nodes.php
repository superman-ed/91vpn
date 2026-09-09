<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 节点上报的「我现在跑的是哪一份规则」。
 *
 * [!!] 在此之前，面板保存规则后说"已保存"就结束了 —— 它不知道节点是不是
 *      真的应用了。节点若因校验失败一直用旧规则跑，服务正常、日志有话说，
 *      而界面上一点迹象都没有。真实发生过（下发缺 credential.uuid，
 *      节点每 10 秒拒一次）。
 *
 * [!!] 两个哈希而不是一个。只存"当前哈希"的话，面板只能看出不一致，
 *      说不清是【还没看到】新规则（拉取失败／网络断）还是
 *      【看到了但用不了】（规则本身有问题）—— 而这两种的处理方式完全不同：
 *      前者去查网络，后者去改规则。
 */
/**
 * [!] 本文件由 relaypanel 原样搬入（ADR-008：中转面板并回 91vpn）。
 * 注释保持原样 —— 那是三边（本表 / 下发 JSON / agent 的 Go 结构体）
 * 冻结过的共识，不要在这里自行增删字段名。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            // 节点正在服务的那份规则的指纹。null = 从未上报（老 agent 或落地节点）。
            $t->string('applied_hash', 32)->nullable();
            // 节点最后一次成功拉到的那份。与 applied_hash 不等 = 看到了没用上。
            $t->string('fetched_hash', 32)->nullable();
            // 卡住的原因。[!] 用 text：内核的构建错误可以很长，
            // 而截断掉的错误信息往往正好丢掉最关键的那半句。
            $t->text('sync_error')->nullable();
            // 真 = 节点当前【没在转发】，正在持续重试。比"落后"严重得多。
            $t->boolean('sync_degraded')->default(false);
            $t->unsignedInteger('sync_rules')->default(0);
            // [!] 单独记上报时间，不复用 last_heartbeat：
            // 心跳是所有节点都发的，这一项只有中转发。混用会让落地节点
            // 看起来"刚刚报告过同步状态"，而它根本没有规则可言。
            $t->timestamp('sync_reported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $t) {
            $t->dropColumn(['applied_hash', 'fetched_hash', 'sync_error',
                'sync_degraded', 'sync_rules', 'sync_reported_at']);
        });
    }
};
