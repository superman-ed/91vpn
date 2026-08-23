<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Node;

class ServerListController extends Controller
{
    /** GET /user/servers */
    public function index()
    {
        $nodes = Node::where('online', true)
            ->where('node_class', '<=', auth()->user()->class)
            ->orderBy('sort')->orderBy('id')->get();

        return view('user.servers', ['nodes' => $nodes]);
    }
}
