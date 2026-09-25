<?php

use App\Models\CrashLog;

// `[!!]` crash_logs.stack 是 TEXT(65,535【字节】),而校验写的是 max:20000
//   ——那是【字符】数。ASCII 堆栈 20000 字符约 20000 字节没事;
//   4 字节字符(emoji)顶满就是 80,000 字节,超出 TEXT 上限,
//   MySQL 开着 STRICT_TRANS_TABLES → 抛错 → HTTP 500。
//   [D] 2026-09-24 实测过:20000 个 🙂 确实 500。
//
// `[!]` 崩溃上报是"发了就不管"的遥测:500 意味着那条报告【直接丢失】,
//   丢的正是内容最长的那些。所以截断而不是拒绝 ——
//   这个做法作者已经用在 message 上(Str::limit 到 490),stack 只是漏了。

it('4 字节字符顶满的堆栈不再 500，而是截断入库', function () {
    $res = $this->postJson('/api/crash', [
        'message' => 'boom', 'platform' => 'android',
        'stack' => str_repeat('🙂', 20000),      // 80,000 字节
    ]);

    expect($res->status())->not->toBe(500, '仍然 500 —— 崩溃报告被丢掉了');
    expect(CrashLog::count())->toBe(1);
    expect(strlen((string) CrashLog::first()->stack))->toBeLessThanOrEqual(65535);
});

it('截断不会把一个字符切成两半', function () {
    $this->postJson('/api/crash', [
        'message' => 'boom', 'platform' => 'android', 'stack' => str_repeat('🙂', 20000),
    ]);

    $stack = (string) CrashLog::first()->stack;
    // mb_strcut 保证切在字符边界上:重新解码不应产生替换字符
    expect(mb_check_encoding($stack, 'UTF-8'))->toBeTrue('截断切坏了字符');
});

it('正常长度的堆栈原样保留', function () {
    $stack = "at Foo.bar(Foo.java:12)\nat Baz.qux(Baz.java:34)";
    $this->postJson('/api/crash', ['message' => 'boom', 'platform' => 'android', 'stack' => $stack]);

    expect((string) CrashLog::first()->stack)->toBe($stack);
});

// `[!]` message 那一侧作者早就处理了(Str::limit 到 490,列宽 500)——
//   这条只是把"已经对的"钉住,防止有人把截断去掉。
it('message 仍然截断到列宽以内', function () {
    $this->postJson('/api/crash', ['message' => str_repeat('E', 1900), 'platform' => 'android']);

    expect(mb_strlen((string) CrashLog::first()->message))->toBeLessThanOrEqual(500);
});
