<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $today = today();
        $paid = Order::where('status', 'paid');

        // 近 14 天每日收入
        $since = today()->subDays(13);
        $byDay = Order::where('status', 'paid')->whereNotNull('paid_at')->where('paid_at', '>=', $since)
            ->get(['amount', 'paid_at'])->groupBy(fn ($o) => $o->paid_at->toDateString());
        $chart = collect(range(13, 0))->map(function ($i) use ($byDay) {
            $d = today()->subDays($i);
            return ['label' => $d->format('m-d'), 'value' => (float) ($byDay->get($d->toDateString())?->sum('amount') ?? 0)];
        });

        return view('admin.dashboard', [
            'userCount' => User::count(),
            'nodeCount' => Node::count(),
            'onlineNodes' => Node::where('online', true)->count(),
            'planCount' => Plan::count(),
            'paidOrders' => (clone $paid)->count(),
            'revenue' => (clone $paid)->sum('amount'),
            'todayUsers' => User::whereDate('created_at', $today)->count(),
            'todayOrders' => (clone $paid)->whereDate('paid_at', $today)->count(),
            'todayRevenue' => (clone $paid)->whereDate('paid_at', $today)->sum('amount'),
            'openTickets' => Ticket::where('status', 'open')->count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'recentOrders' => Order::with('user', 'plan')->latest()->limit(8)->get(),
            'chart' => $chart,
            'chartMax' => max(1, (float) $chart->max('value')),
        ]);
    }
}
