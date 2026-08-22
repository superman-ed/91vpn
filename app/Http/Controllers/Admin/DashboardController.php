<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'userCount' => User::count(),
            'nodeCount' => Node::count(),
            'planCount' => Plan::count(),
            'paidOrders' => Order::where('status', 'paid')->count(),
            'revenue' => Order::where('status', 'paid')->sum('amount'),
        ]);
    }
}
