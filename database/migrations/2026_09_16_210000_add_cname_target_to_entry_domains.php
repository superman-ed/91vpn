<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 入口域名加一层 CNAME（见 docs/decisions/entry-dispatch.md · D-5）。
 *
 *   门牌         entry.example.com     CNAME →  ← 订阅发这个，永不变
 *   线路池标签    hk1.example.net       A     →  ← 改 A 记录改这个
 *   中转 IP       <中转当前的公网 IP>
 *
 * `[D]` SoCloud 就是两层：cp.paeadiy.com 是 CNAME，指向 gy1.paeadiy.com。
 *
 * 为什么值得多这一层（三条，都是省事或省钱）：
 *   · 一个标签可被【多个门牌】共用 —— 主域名 + 备用域名一起跟着走，改一次 A 记录而不是 N 次；
 *   · 线路解析（按运营商分流）只需配在标签上，付费 DNS 套餐是按域名买的，
 *     N 个门牌共用一个标签 = 只买一份；
 *   · TTL 可分层：门牌 CNAME 长 TTL（几乎不变），标签 A 短 TTL（换 IP 要快）。
 *
 * 代价：冷缓存时多一次 DNS 往返。
 *
 * `[!]` 本列【不加 unique】—— 多个门牌指向同一个标签正是它存在的理由。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_domains', function (Blueprint $t) {
            // 门牌 CNAME 到的线路池标签。为空 = 单层（门牌自己挂 A 记录）。
            $t->string('cname_target', 253)->nullable()->after('pointed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('entry_domains', function (Blueprint $t) {
            $t->dropColumn('cname_target');
        });
    }
};
