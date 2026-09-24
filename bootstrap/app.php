<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   // 客户端对接 API(无状态,Bearer api_token)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 信任 Cloudflare 隧道/反代转发的头，使 HTTPS/host 识别正确
        $middleware->web(append: [App\Http\Middleware\TurboRedirects::class, App\Http\Middleware\CaptureUtm::class, App\Http\Middleware\NoindexNonCanonicalHost::class]);
        // [!] 必须 prepend:它要排在 Authenticate 前面,否则走错主机名的请求
        // 会先被跳到登录页。见 Middleware\AdminHost 的说明。
        $middleware->web(prepend: [App\Http\Middleware\AdminHost::class]);
        $middleware->alias([
            'node.secret' => App\Http\Middleware\NodeSecret::class,
            'admin' => App\Http\Middleware\AdminOnly::class,
            'client.token' => App\Http\Middleware\ClientToken::class,
        ]);
        // 节点 WebAPI、支付网关异步回调是机器对机器调用，豁免 CSRF
        $middleware->validateCsrfTokens(except: ['mod_mu/*', 'pay/epay/*', 'api/*']);
        $middleware->trustProxies(at: '*', headers:
            Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // tinker 正常退出抛的是 Psy\Exception\BreakException（"Exit:  Goodbye"），
        // 默认会被当成【未捕获异常】写成 ERROR。它是退出方式，不是异常情况，
        // 不该进错误日志 —— 这一条按语义成立。
        //
        // `[!!]` 但要说清它【证明了什么、没证明什么】：
        // `[D]` 日志里有 28264 条这样的 ERROR，全部集中在 2026-09-09/10 两天，
        //      最后一条 09-10 20:01:29，此后零新增。
        // `[D]` 三种方式都【复现不出】触发条件：tools/repro 的文件模式 tinker、
        //      tinker --execute、交互式 exit —— 都不产生该行。
        // 所以这一条是【防复发的保险】，不是对某个活跃来源的修复。
        // 第一次验证时我差点报成功：修复后未新增，而【撤回修复也未新增】——
        // 停止的原因不是这行代码。跑了对照才发现（ROUND 判据 112）。
        $exceptions->dontReport([
            \Psy\Exception\BreakException::class,
        ]);
    })->create();
