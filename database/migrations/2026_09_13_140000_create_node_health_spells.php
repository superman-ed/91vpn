<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 节点的「存活区段」（spell）—— 一段连续处于可用状态的时间。
 *
 * `[!!]` 这张表的目的是回答一个此前只能写 `[?]` 的问题：
 * **单位观察时间内，中转的失效事件是不是明显比落地多？**
 *
 * 它【不是】给平均值用的。"中转平均活 17 天、落地 31 天"这种说法在
 * 当前样本下是错的，因为：
 *   - 大量节点到观察结束都没失效（右删失 / censored）——
 *     它们的真实存活时间只知道"至少这么久"；
 *   - 节点上线时间不同、数量不同，直接比平均值会被这两样带偏。
 * 所以要比的是【失效事件数 / 暴露时间(node-days)】，不是平均存活。
 *
 * `[!!]` 人工下线【不是失效，是删失】。把它算成失效，等于把运维动作
 * 混进"环境把它弄死了"里 —— 而那正是我们要测的东西。
 *
 * 与建议的字段表有两处刻意的不同，都是为了让非法状态无法表示：
 *
 *   1. 用 ended_at + outcome，而不是 failed_at / censored_at 两列。
 *      两列的写法允许"两个都填"和"结束了但都没填"两种非法状态，
 *      而它们不会报错，只会让统计悄悄算错。
 *   2. 多一个 left_truncated：观察开始时【已经活着】的节点，
 *      它的真实起点在观察窗口之前且不可知（左截断）。
 *      不标出来的话，这些区段的存活时间会被系统性低估 ——
 *      这与右删失是对称的一个陷阱，方向相反。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_health_spells', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('node_id')->index();
            // `[!]` 角色按【区段开始时】快照。节点的 role 可以改，
            // 改了之后这一段的归属不该跟着变 —— 否则分组统计会追溯性地失真。
            $t->string('role', 16);

            $t->timestamp('first_healthy_at');
            $t->boolean('left_truncated')->default(false);
            $t->timestamp('last_healthy_at');
            $t->unsignedInteger('observations')->default(0);
            // `[!!]` 判定依据是"未知"的采样单独计数。中转所在规则没开健康检查时，
            // 「到落地」那一层永远是 unknown —— 那种区段的"存活"是【推定】的，
            // 不是观测到的。不分开记的话，一堆推定值会混进结论里。
            $t->unsignedInteger('unknown_observations')->default(0);

            // 结束：ended_at 记的是【最后一次确认健康之后的第一次失败采样】的时刻，
            // 不是检出时刻 —— 真实失效发生在两次采样之间，取前者更接近。
            $t->timestamp('ended_at')->nullable();
            $t->string('outcome', 16)->nullable();   // failed | censored
            $t->string('reason', 24)->nullable();
            // 连续失败采样计数：一次网络抖动不该被记成一次"死亡"。
            $t->unsignedTinyInteger('misses')->default(0);
            $t->timestamp('first_miss_at')->nullable();

            $t->timestamps();
            // 一个节点同时最多一个未结束的区段。
            $t->index(['node_id', 'ended_at']);
            $t->index(['role', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_health_spells');
    }
};
