<?php
/**
 * 复现脚本的库守卫 —— 由 tools/repro 自动拼在脚本前面，不要手动 require。
 *
 * `[!!]` 存在的理由：`php artisan tinker` 读 .env，而 .env 里
 * DB_DATABASE=vpn（真实库）。"写复现脚本时脑子里想的是测试环境、
 * 手上执行的是生产连接"是一个【没有任何提示】的失误 ——
 * 2026-09-12 我就这么往真实库里造了两个节点一个用户，还进了真实订阅，
 * 是顺手 dump 了一次订阅看见名字才发现的（ROUND-2026-09 判据 76）。
 *
 * 所以这里不是"提醒小心"，是【默认拒绝】：
 * 连不到 vpn_test 就直接退出，除非显式给了 REPRO_ALLOW_REAL=1。
 */

$db = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
$allowReal = getenv('REPRO_ALLOW_REAL') === '1';

if (! $allowReal && $db !== 'vpn_test') {
    fwrite(STDERR, <<<TXT

    ✋ 拒绝执行：当前连的是「{$db}」，不是 vpn_test。

       复现/造数脚本默认只能跑在测试库。之所以做成硬拒绝而不是提示，
       是因为一旦写错，现象是【没有现象】—— 脚本正常跑完，数据静静地
       进了真实库和真实订阅。

       确实要动真实库（清理、核对线上状态）就显式声明：
           tools/repro --real 你的脚本.php

    TXT);
    exit(1);
}

fprintf(STDERR, "── 库: %s%s\n", $db, $allowReal ? '  ⚠️ 真实库（--real）' : '');
