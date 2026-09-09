<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Node;

class ServerListController extends Controller
{
    /** GET /user/servers —— 全量展示所有在线节点(网页端仅展示、无法连接,不按等级过滤/加锁) */
    public function index()
    {
        $nodes = Node::userVisible()   // D-1：中转/跳板/入口不进用户面
            ->where('online', true)
            ->where('enabled', true)   // 排空/维护中的节点不展示
            ->orderBy('sort')->orderBy('id')->get();

        return view('user.servers', ['nodes' => $nodes]);
    }
}
