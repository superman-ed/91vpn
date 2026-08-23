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

        $downloads = [
            ['os' => 'Windows', 'icon' => 'fab fa-windows', 'name' => 'Clash Verge Rev', 'url' => 'https://github.com/clash-verge-rev/clash-verge-rev/releases'],
            ['os' => 'macOS', 'icon' => 'fab fa-apple', 'name' => 'Clash Verge Rev', 'url' => 'https://github.com/clash-verge-rev/clash-verge-rev/releases'],
            ['os' => 'Android', 'icon' => 'fab fa-android', 'name' => 'FlClash', 'url' => 'https://github.com/chen08209/FlClash/releases'],
            ['os' => 'iOS', 'icon' => 'fab fa-app-store-ios', 'name' => 'Shadowrocket', 'url' => 'https://apps.apple.com/app/shadowrocket/id932747118'],
        ];

        return view('user.downloads', array_merge($links, ['downloads' => $downloads]));
    }
}
