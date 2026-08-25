<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $orders = Order::with(['user', 'plan'])
            ->when(in_array($status, ['paid', 'pending', 'queued', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'counts' => [
                'all' => Order::count(),
                'paid' => Order::where('status', 'paid')->count(),
                'pending' => Order::where('status', 'pending')->count(),
                'queued' => Order::where('status', 'queued')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
            ],
        ]);
    }
}
