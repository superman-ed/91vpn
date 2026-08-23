<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TurboRedirects
{
    /**
     * Turbo 要求表单(非GET)提交后的重定向用 303 See Other，
     * 否则会把重定向目标当作表单响应内容处理。Laravel 默认 302。
     * 这里把非GET请求的 302 统一改成 303（对普通浏览器也完全兼容）。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')
            && $response->getStatusCode() === 302) {
            $response->setStatusCode(303);
        }

        return $response;
    }
}
