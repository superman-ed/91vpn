<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 中转到落地那一跳的【时延与劣化】。
 *
 * `[!!]` 与 alive 分开存，理由同 reported_dest_degraded 与 reported_dest_up：
 * 劣化先于失败，而失败才是我们唯一会看到的。一条从 2ms 变成 40ms 的
 * 中转→落地跳在 alive 上完全不可见，而每条用户连接都在多付那 38ms。
 *
 * `[!]` 判据在节点侧，是【自身相对】的（中位数 ≥ 自身基线 3 倍且差值 ≥ 30ms）。
 * 不在面板这边用绝对毫秒判：中转到落地的时延取决于选址，
 * 港→美 200ms 完全正常、同机房 1ms 也正常，绝对阈值必然两头错。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rule_outbound_status', function (Blueprint $t) {
            $t->unsignedInteger('delay_ms')->default(0)->after('live');
            $t->boolean('slow')->default(false)->after('delay_ms');
        });
    }

    public function down(): void
    {
        Schema::table('rule_outbound_status', function (Blueprint $t) {
            $t->dropColumn(['delay_ms', 'slow']);
        });
    }
};
