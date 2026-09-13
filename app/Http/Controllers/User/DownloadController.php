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

        // `[!]` 从后台读，不再硬编码。url 为空仍然列出来并显示「即将推出」——
        // 那是一个有意义的状态，不是缺数据，把它整个藏掉会让人以为不支持该平台。
        $official = \App\Models\ClientDownload::visible()->get()
            ->map(fn ($d) => [
                'os' => $d->platform, 'label' => $d->label, 'icon' => $d->icon,
                'url' => $d->url, 'version' => $d->version, 'note' => $d->note,
            ])->all();

        return view('user.downloads', array_merge($links, [
            'official' => $official,
        ]));
    }
}
