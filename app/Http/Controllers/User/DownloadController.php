<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\ClientLinks;

class DownloadController extends Controller
{
    /** GET /user/downloads —— 客户端下载 + 订阅导入主入口 */
    public function index()
    {
        $links = ClientLinks::for(auth()->user());

        // 91VPN 定制客户端（第二阶段自研，暂占位）
        $official = [
            ['os' => 'Windows', 'icon' => 'fab fa-windows', 'url' => null],
            ['os' => 'macOS', 'icon' => 'fab fa-apple', 'url' => null],
            ['os' => 'Android', 'icon' => 'fab fa-android', 'url' => null],
            ['os' => 'iOS', 'icon' => 'fab fa-app-store-ios', 'url' => null],
        ];

        return view('user.downloads', array_merge($links, [
            'official' => $official,
        ]));
    }
}
