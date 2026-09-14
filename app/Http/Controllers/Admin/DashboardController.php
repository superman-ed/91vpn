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
        // 已收款订单:paid + queued(排队中的钱已收,只是套餐排队等激活),营收/趋势按此口径
        //
        // `[!!]` 排除后台「开通」建的单(pay_method=admin)。它是管理员直接给用户
        // 开套餐时手工建的一条 amount=0 记录 —— 一分钱没付,而这张卡片写的是
        // 【已支付订单】。金额是 0 所以"累计收入"本来就不受影响,受影响的是【单数】:
        // 拿它做测试(目前的实际用途)会把成交单数撑大,而那个数字上线后是要看的。
        //
        // `[!!]` 不能写成 where('pay_method', '!=', 'admin') ——
        // SQL 里 NULL != 'admin' 的结果是 NULL(不是 true),那样会把
        // pay_method 为空的历史订单【一起排除掉】,单数反而变小。
        $paid = Order::whereIn('status', ['paid', 'queued'])
            ->where(fn ($q) => $q->whereNull('pay_method')->orWhere('pay_method', '!=', 'admin'));

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
        $byDay = (clone $paid)->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['amount', 'paid_at'])->groupBy(fn ($o) => $o->paid_at->toDateString());

        $chart = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $chart->push(['label' => $d->format('m-d'), 'value' => (float) ($byDay->get($d->toDateString())?->sum('amount') ?? 0)]);
        }

        return view('admin.dashboard', [
            // [!] 上线自检:一个新注册的用户现在能不能真的用起来。
            // 每一项单独都查得到,但"合起来够不够开张"此前没有页面回答。
            'readiness' => app(\App\Services\ServiceReadiness::class)->check(),
            // `[!!]` 钱和货对不上的几种形态。判据取自【真实流程不会产生的组合】,
            // 不是泛泛的"已付未发货"—— 排队中的订单是正常的,算进去就天天误报。
            'orderAnomalies' => app(\App\Services\OrderAnomalies::class)->check(),
            'userCount' => User::count(),
            'onlineUsers' => \App\Models\AliveIp::where('last_seen', '>=', now()->subSeconds(\App\Models\AliveIp::ONLINE_WINDOW))->distinct()->count('user_id'),
            'activeToday' => User::whereDate('last_used_at', $today)->count(),
            'todayTraffic' => (int) \App\Models\NodeDailyTraffic::whereDate('date', $today)->sum(\DB::raw('u + d')),
            'totalTraffic' => (int) \App\Models\NodeDailyTraffic::sum(\DB::raw('u + d')),
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
