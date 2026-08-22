<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['user', 'plan'])->latest()->paginate(30);

        return view('admin.orders.index', ['orders' => $orders]);
    }
}
