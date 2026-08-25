<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = today();
        $paid = Order::where('status', 'paid');

        // 日期区间(默认近 14 天),限制最长 180 天
        $to = $this->parseDate($request->query('to'), $today);
        $from = $this->parseDate($request->query('from'), $today->copy()->subDays(13));
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 180) {
            $from = $to->copy()->subDays(180);
        }

        // 区间每日收入
        $byDay = Order::where('status', 'paid')->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['amount', 'paid_at'])->groupBy(fn ($o) => $o->paid_at->toDateString());

        $chart = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $chart->push(['label' => $d->format('m-d'), 'value' => (float) ($byDay->get($d->toDateString())?->sum('amount') ?? 0)]);
        }

        return view('admin.dashboard', [
            'userCount' => User::count(),
            'nodeCount' => Node::count(),
            'onlineNodes' => Node::where('online', true)->count(),
            'planCount' => Plan::count(),
            'paidOrders' => (clone $paid)->count(),
            'revenue' => $revenue = (clone $paid)->sum('amount'),
            'totalRebate' => $totalRebate = \App\Models\Payback::sum('amount'),
            'netProfit' => $revenue - $totalRebate,
            'todayUsers' => User::whereDate('created_at', $today)->count(),
            'todayOrders' => (clone $paid)->whereDate('paid_at', $today)->count(),
            'todayRevenue' => (clone $paid)->whereDate('paid_at', $today)->sum('amount'),
            'openTickets' => Ticket::where('status', 'open')->count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'recentOrders' => Order::with('user', 'plan')->latest()->limit(8)->get(),
            'chart' => $chart,
            'chartMax' => max(1, (float) $chart->max('value')),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            // 区间统计
            'rangeRevenue' => (float) $chart->sum('value'),
            'rangeOrders' => (clone $paid)->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            'rangeUsers' => User::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
        ]);
    }

    private function parseDate(?string $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $default->copy();
        } catch (\Throwable $e) {
            return $default->copy();
        }
    }
}
