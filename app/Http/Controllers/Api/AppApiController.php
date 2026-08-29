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
}
