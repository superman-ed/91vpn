<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Node;

class ServerListController extends Controller
{
    /** GET /user/servers —— 全量展示所有在线节点(网页端仅展示、无法连接,不按等级过滤/加锁) */
    public function index()
    {
        $nodes = Node::where('online', true)
            ->orderBy('sort')->orderBy('id')->get();

        return view('user.servers', ['nodes' => $nodes]);
    }
}
