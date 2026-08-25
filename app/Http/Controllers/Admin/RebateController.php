<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payback;
use App\Models\User;
use Illuminate\Http\Request;

class RebateController extends Controller
{
    /** GET /admin/rebates —— 全站返佣明细（Payback 账本：受益人 ← 下线 + 金额 + 订单） */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        // 邮箱可命中受益人或下线任一方
        $base = Payback::query()
            ->when($q, function ($query) use ($q) {
                $ids = User::where('email', 'like', "%{$q}%")->pluck('id');
                $query->where(fn ($w) => $w->whereIn('user_id', $ids)->orWhereIn('from_user_id', $ids));
            })
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to));

        $rebates = (clone $base)->with('user', 'fromUser', 'order')->latest()->paginate(30)->withQueryString();

        // 受益人 TOP5
        $topEarners = (clone $base)
            ->selectRaw('user_id, sum(amount) as total, count(*) as cnt')
            ->groupBy('user_id')->orderByDesc('total')->take(5)->get()
            ->map(fn ($r) => ['user' => User::find($r->user_id), 'total' => (float) $r->total, 'cnt' => $r->cnt]);

        return view('admin.rebates.index', [
            'rebates' => $rebates,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'sumAmount' => (float) (clone $base)->sum('amount'),
            'countAll' => (clone $base)->count(),
            'earnerCount' => (clone $base)->distinct('user_id')->count('user_id'),
            'topEarners' => $topEarners,
        ]);
    }
}
