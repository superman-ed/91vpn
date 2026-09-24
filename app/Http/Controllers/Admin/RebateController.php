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

        // 搜索可命中受益人或下线任一方
        // `[!!]` 曾经【只匹配 email】,而本产品注册不收邮箱 —— 客户端注册的用户
        //   email 为空,这个搜索框对他们永远搜不到(owner 自己的账号就是这样)。
        //   口径与「用户管理」页一致:username / email / name 任一命中。
        $base = Payback::query()
            ->when($q, function ($query) use ($q) {
                $ids = User::where(fn ($w) => $w->where('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%"))->pluck('id');
                $query->where(fn ($w) => $w->whereIn('user_id', $ids)->orWhereIn('from_user_id', $ids));
            })
            ->dateBetween($from, $to);

        $rebates = (clone $base)->with('user', 'fromUser', 'order')->latest()->paginate(30)->withQueryString();

        // 受益人 TOP5
        $topRows = (clone $base)
            ->selectRaw('user_id, sum(amount) as total, count(*) as cnt')
            ->groupBy('user_id')->orderByDesc('total')->take(5)->get();
        $earnerUsers = User::whereIn('id', $topRows->pluck('user_id'))->get()->keyBy('id');   // 一次预取,避免 N+1
        $topEarners = $topRows->map(fn ($r) => ['user' => $earnerUsers->get($r->user_id), 'total' => (float) $r->total, 'cnt' => $r->cnt]);

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
