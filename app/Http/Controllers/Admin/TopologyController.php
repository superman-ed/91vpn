<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Topology;

class TopologyController extends Controller
{
    public function index(Topology $topo)
    {
        return view('admin.topology', $topo->build());
    }
}
