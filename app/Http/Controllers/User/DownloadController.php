<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class DownloadController extends Controller
{
    /** GET /user/downloads —— 客户端下载和教程 */
    public function index()
    {
        // 各平台推荐客户端（指向官方开源客户端下载页）
        $clients = [
            ['os' => 'Windows', 'icon' => 'fab fa-windows', 'name' => 'Clash Verge Rev', 'url' => 'https://github.com/clash-verge-rev/clash-verge-rev/releases'],
            ['os' => 'macOS', 'icon' => 'fab fa-apple', 'name' => 'Clash Verge Rev', 'url' => 'https://github.com/clash-verge-rev/clash-verge-rev/releases'],
            ['os' => 'Android', 'icon' => 'fab fa-android', 'name' => 'FlClash', 'url' => 'https://github.com/chen08209/FlClash/releases'],
            ['os' => 'iOS', 'icon' => 'fab fa-app-store-ios', 'name' => 'Shadowrocket（App Store）', 'url' => 'https://apps.apple.com/app/shadowrocket/id932747118'],
        ];

        return view('user.downloads', ['clients' => $clients]);
    }
}
