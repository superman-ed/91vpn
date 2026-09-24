<?php

use App\Models\BalanceLog;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 两个缺陷,都是"安静地不对"那一类:
//   ① 类型清单在【三个地方】各写一份(BalanceLog 之外还有 FinanceController
//      和 Blade)。2026-09-24 加 refund 时只改了 Blade,于是新类型在筛选里被
//      无视(in_array 不中就当没筛过)、在导出里显示成原始英文。全程不报错。
//   ② 按用户搜索【只匹配 email】,而本产品注册不收邮箱
//      (AuthApiController::register 只要 username + password)——
//      客户端注册的用户 email 为空,搜索框对他们永远搜不到。
//      实测:owner 自己的账号 summer 就没有邮箱。
// ─────────────────────────────────────────────────────────────────

function fnAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'password' => 'adminpass123']);
}

/** 造一个【没有邮箱】的用户 —— 客户端注册出来的就是这样 */
function fnNoEmailUser(string $username): User
{
    return User::factory()->create([
        'username' => $username, 'email' => null, 'password' => 'x12345678',
    ]);
}

function fnLog(User $u, string $type, float $amount): BalanceLog
{
    return BalanceLog::create([
        'user_id' => $u->id, 'amount' => $amount, 'type' => $type,
        'balance_after' => 100, 'remark' => '测试',
    ]);
}

it('按用户名能搜到没有邮箱的用户', function () {
    $u = fnNoEmailUser('summer');
    fnLog($u, 'recharge', 50);
    fnLog(fnNoEmailUser('someoneelse'), 'recharge', 70);

    $html = $this->actingAs(fnAdmin())->get('/admin/finance?q=summer')->assertOk()->getContent();

    expect($html)->toContain('50.00');
    // `[!]` 不能写成 not->toContain('70.00', '消息') —— Pest 的 toContain 是
    //   【可变参数】(多个 needle),那句消息会变成第二个 needle,而它永远不在
    //   HTML 里,于是整条断言恒真。本会话踩过一次,三处。
    expect(str_contains($html, '70.00'))->toBeFalse('搜索没有生效 —— 别人的流水也出来了');
});

it('流水行显示 ident，不是空邮箱占位', function () {
    $u = fnNoEmailUser('summer');
    fnLog($u, 'recharge', 50);

    $csv = $this->actingAs(fnAdmin())->get('/admin/finance/export')->assertOk()->streamedContent();

    expect($csv)->toContain('summer');
});

// `[!!]` 这一条钉住"三份清单"那个坑:控制器与视图都必须引唯一来源。
it('类型清单只有一处，控制器与视图都引它', function () {
    expect(BalanceLog::TYPE_NAME)->toHaveKey('refund');
    expect(BalanceLog::types())->toContain('refund');

    // 控制器里不许再出现自己的副本
    $src = file_get_contents(base_path('app/Http/Controllers/Admin/FinanceController.php'));
    expect($src)->not->toMatch("/private const TYPE_?NAME/", 'FinanceController 又自己写了一份类型清单');
    expect($src)->not->toMatch("/private const TYPES/");

    $view = file_get_contents(base_path('resources/views/admin/finance/index.blade.php'));
    expect($view)->toContain('BalanceLog::TYPE_NAME');
});

it('按 refund 类型筛选是真的筛，不是被无视', function () {
    $u = fnNoEmailUser('payer');
    fnLog($u, 'consume', -30);
    fnLog($u, 'refund', 30);

    $html = $this->actingAs(fnAdmin())->get('/admin/finance?type=refund')->assertOk()->getContent();

    expect($html)->toContain('退款');
    expect(str_contains($html, '-30.00'))->toBeFalse('consume 那条也出来了 —— 筛选被无视了');
});

it('返佣页也能按用户名搜到没有邮箱的用户', function () {
    $earner = fnNoEmailUser('inviter');
    $downline = fnNoEmailUser('downline');
    \App\Models\Payback::create([
        'user_id' => $earner->id, 'from_user_id' => $downline->id, 'amount' => 5,
    ]);

    $html = $this->actingAs(fnAdmin())->get('/admin/rebates?q=inviter')->assertOk()->getContent();

    expect($html)->toContain('inviter');
});
