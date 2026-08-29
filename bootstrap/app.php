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
        $middleware->web(append: [App\Http\Middleware\TurboRedirects::class, App\Http\Middleware\CaptureUtm::class]);
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
        //
    })->create();
