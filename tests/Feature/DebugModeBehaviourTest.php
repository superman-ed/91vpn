<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * F-1 变更② 依赖的两条性质，钉成测试。
 *
 * `[!!]` 关掉 APP_DEBUG 的前提是：异常信息【不再回给客户端】，但【仍然进日志】。
 * 这两条此前只有 [S]（读框架源码）。在生产上触发一次未捕获异常才能拿到 [D]，
 * 而那是不该做的事 —— 所以在这里取证：同一条异常，只切 app.debug，比较两次响应。
 *
 * `[!]` 这也是一条【常驻检查】：哪天升级框架改了这个行为，这里会红，
 * 而不是等到某次线上 500 把堆栈泄给用户才发现。
 */
function dbgRoute(): string
{
    $uri = '/__audit_probe_'.bin2hex(random_bytes(4));
    Route::get($uri, function () {
        throw new RuntimeException('AUDIT_PROBE_SECRET_MARKER');
    });

    return $uri;
}

it('debug=true 时，响应里【有】异常细节', function () {
    config(['app.debug' => true]);
    $uri = dbgRoute();

    // `[!]` 不调 withoutExceptionHandling —— 那会让异常直接抛出，
    // 而我们要看的正是【处理器渲染出来的响应】。
    $r = $this->get($uri);

    // 这条是【对照】：没有它，下面几条"不包含堆栈"的断言可能是空真 ——
    // 异常压根没发生也会通过。
    expect($r->status())->toBe(500)
        ->and($r->getContent())->toContain('AUDIT_PROBE_SECRET_MARKER');
});

it('debug=false 时，响应里【没有】异常消息、文件路径或堆栈', function () {
    config(['app.debug' => false]);
    $uri = dbgRoute();

    $r = $this->get($uri);
    $body = $r->getContent();

    expect($r->status())->toBe(500);
    // `[!!]` 三样都不能出现：异常消息、源码路径、堆栈帧
    expect($body)->not->toContain('AUDIT_PROBE_SECRET_MARKER')
        ->and($body)->not->toContain('/var/www/html/tests')
        ->and($body)->not->toContain('vendor/laravel/framework');
});

it('debug=false 时，JSON 请求只回 Server Error，不带 file/line/trace', function () {
    config(['app.debug' => false]);
    $uri = dbgRoute();

    $json = $this->getJson($uri);

    expect($json->status())->toBe(500);
    $d = $json->json();
    expect($d)->toHaveKey('message')
        ->and($d['message'])->toBe('Server Error')
        ->and($d)->not->toHaveKey('trace')
        ->and($d)->not->toHaveKey('file')
        ->and($d)->not->toHaveKey('line')
        ->and($d)->not->toHaveKey('exception');
});

it('`[!!]` debug=false 时异常【仍然写进日志】—— 这是关掉它的前提', function () {
    config(['app.debug' => false]);
    $uri = dbgRoute();

    $logged = [];
    Log::listen(function ($e) use (&$logged) { $logged[] = $e->message; });

    $this->get($uri);

    expect(collect($logged)->contains(fn ($m) => str_contains($m, 'AUDIT_PROBE_SECRET_MARKER')))
        ->toBeTrue('关掉 debug 之后异常没有进日志 —— 那就变成了"看不见也不知道"');
});
