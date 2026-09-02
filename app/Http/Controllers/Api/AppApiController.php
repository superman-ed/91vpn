<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class AppApiController extends Controller
{
    /**
     * GET /api/app/version —— 客户端版本检查(公开,登录前也可调)。
     * 数据从站点设置读取,后台可配;未配置则字段为空,客户端据此判断是否有更新。
     */
    public function version()
    {
        return response()->json(['ret' => 1, 'data' => [
            'latest' => setting('app_version', ''),
            'force' => setting('app_force_update', '0') === '1',
            'notes' => setting('app_update_notes', ''),
            'downloads' => [
                'android' => setting('app_download_android', ''),
                'windows' => setting('app_download_windows', ''),
                'macos' => setting('app_download_macos', ''),
                'ios' => setting('app_download_ios', ''),
            ],
        ]]);
    }

    /**
     * GET /api/app/config —— 客户端运行时配置(公开)。目前提供在线客服 Crisp 配置。
     * crisp_website_id 与网页版共用(网页 JS 中本就明文);未配置则为空,客户端据此隐藏在线客服入口。
     */
    public function config()
    {
        // API 域名容灾列表(后台可编辑,一行一个;客户端拉取后缓存,连不上主域名自动切换)
        $hosts = collect(preg_split('/\r\n|\r|\n/', (string) setting('api_hosts', '')))
            ->map(fn ($h) => trim($h))
            ->filter(fn ($h) => $h !== '')
            ->values()->all();

        return response()->json(['ret' => 1, 'data' => [
            'crisp' => [
                'website_id' => setting('crisp_website_id', ''),
                'bind_identity' => setting('crisp_bind_identity', '0') === '1',
            ],
            'legal' => [
                'terms' => setting('terms_content', ''),
                'privacy' => setting('privacy_content', ''),
            ],
            'hosts' => $hosts,
        ]]);
    }
}
