<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BillingService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $dateFilter = function ($query) use ($from, $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        };

        $orders = Order::with(['user', 'plan'])
            ->when(in_array($status, ['paid', 'pending', 'queued', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"));
                    if (ctype_digit((string) $q)) {
                        $w->orWhere('id', (int) $q);
                    }
                });
            })
            ->where($dateFilter)
            ->latest()->paginate(30)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'counts' => [
                'all' => Order::count(),
                'paid' => Order::where('status', 'paid')->count(),
                'pending' => Order::where('status', 'pending')->count(),
                'queued' => Order::where('status', 'queued')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
            ],
            'totalRevenue' => $totalRevenue = Order::where('status', 'paid')->sum('amount'),
            'totalRebate' => $totalRebate = \App\Models\Payback::sum('amount'),
            'netProfit' => $totalRevenue - $totalRebate,
            'todayRevenue' => Order::where('status', 'paid')->whereDate('paid_at', today())->sum('amount'),
        ]);
    }

    /** 手动标记已支付并发货(线下/补单用) */
    public function markPaid(Order $order, BillingService $billing)
    {
        if ($order->status !== 'pending') {
            return back()->with('status', '该订单非待支付状态');
        }
        $billing->completeOrder($order, 'manual');
        audit('order.mark_paid', "手动标记订单 {$order->order_no} 已支付并发货", $order);

        return back()->with('status', "订单 #{$order->id} 已标记支付并发货");
    }

    /** 取消待支付订单 */
    public function cancel(Order $order)
    {
        if ($order->status !== 'pending') {
            return back()->with('status', '仅待支付订单可取消');
        }
        $order->update(['status' => 'cancelled']);
        audit('order.cancel', "取消订单 {$order->order_no}", $order);

        return back()->with('status', "订单 #{$order->id} 已取消");
    }
}
