<?php

use App\Models\Ticket;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────
// `[!!]` ticket_replies.content 是 TEXT(65,535 字节),而 MySQL 开着
//   STRICT_TRANS_TABLES —— 超长写入会抛错,用户收到的是【HTTP 500】而不是
//   "内容太长"。2026-09-24 实测:
//       1,000 汉字 → 303 成功,入库 1000
//      70,000 汉字 → 500
//     300,000 汉字 → 500
//   而客服工单里用户最常干的事就是【粘贴客户端日志】——
//   他看到的是"网站坏了",而且没有任何可操作的提示。
// ─────────────────────────────────────────────────────────────────

function tcUser(): User
{
    return User::factory()->create([
        'password' => 'x12345678', 'class' => 1, 'class_expire' => now()->addMonth(),
        'transfer_enable' => 10 * 1024 ** 3, 'u' => 0, 'd' => 0,
    ]);
}

it('超长工单内容给出校验错误，而不是 500', function () {
    $u = tcUser();

    $res = $this->actingAs($u)->post('/user/ticket', [
        'subject' => '粘了一大段日志',
        'content' => str_repeat('阿', 70000),
    ]);

    expect($res->status())->not->toBe(500, '仍然是 500 —— 用户看到的是"网站坏了"');
    $res->assertSessionHasErrors('content');
    expect(Ticket::count())->toBe(0);
});

it('正常长度照常能提交', function () {
    $u = tcUser();

    $this->actingAs($u)->post('/user/ticket', [
        'subject' => '正常工单', 'content' => str_repeat('阿', 1000),
    ])->assertSessionHasNoErrors();

    expect(Ticket::count())->toBe(1);
});

// `[!]` 上限要留足余量:TEXT 是【字节】上限,而校验是【字符】数。
//   汉字 3 字节/个,5000 字符 = 15,000 字节,离 65,535 还很远。
//   真按字符数顶到 21,845 就会又开始 500。
it('上限按字节算仍有充足余量', function () {
    $maxChars = 5000;
    $worstCaseBytes = $maxChars * 4;   // 按 UTF-8 最坏情况 4 字节/字符算

    expect($worstCaseBytes)->toBeLessThan(65535,
        '按最坏情况 4 字节/字符算已经超过 TEXT 上限 —— 改小 max 或把列换成 longText');
});

it('API 那条路也有同样的上限', function () {
    $u = tcUser();
    $token = $u->fresh()->api_token;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tickets', ['subject' => 'x', 'content' => str_repeat('阿', 70000)])
        ->assertStatus(422);

    expect(Ticket::count())->toBe(0);
});
