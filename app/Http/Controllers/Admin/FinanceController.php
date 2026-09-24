<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceLog;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    /**
     * `[!]` 类型清单不在这里维护 —— 唯一来源是 BalanceLog::TYPE_NAME。
     * 这里曾经有一份副本,加 refund 时漏改,导致筛选被无视、导出显示英文。
     */

    /**
     * 按用户搜索的口径。
     *
     * `[!!]` 曾经【只匹配 email】。而本产品的注册【不收邮箱】
     * (AuthApiController::register 只要 username + password) ——
     * 客户端注册的用户 email 是空的,于是这个搜索框对他们【永远搜不到】。
     * 实测:owner 自己的账号 summer 就没有邮箱。
     * 口径改成与「用户管理」页一致:username / email / name 任一命中。
     */
    private function matchUser($query, ?string $q)
    {
        return $query->when($q, fn ($b) => $b->whereHas('user', fn ($u) => $u->where(
            fn ($w) => $w->where('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
        )));
    }

    /** GET /admin/finance/export —— 按当前筛选导出资金流水 CSV */
    public function export(Request $request)
    {
        $type = $request->query('type');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = $this->matchUser(BalanceLog::query(), $q)
            ->when(in_array($type, BalanceLog::types(), true), fn ($b) => $b->where('type', $type))
            ->dateBetween($from, $to);

        $header = ['时间', '用户', '类型', '变动', '变动后余额', '关联订单', '交易号', '备注'];
        $rows = (function () use ($query) {
            foreach ($query->with('user', 'order')->latest()->cursor() as $l) {
                yield [
                    $l->created_at?->format('Y-m-d H:i:s'),
                    $l->user?->ident() ?? '—',   // `[!]` 不用 email:多数用户没有
                    BalanceLog::TYPE_NAME[$l->type] ?? $l->type,
                    number_format((float) $l->amount, 2),
                    number_format((float) $l->balance_after, 2),
                    $l->order?->order_no ?? '',
                    $l->trade_no ?? $l->order?->trade_no ?? '',
                    $l->remark,
                ];
            }
        })();

        audit('finance.export', '导出资金流水 CSV');

        return csv_download('finance_'.now()->format('Ymd_His').'.csv', $header, $rows);
    }

    public function index(Request $request)
    {
        $type = $request->query('type');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        // 合计随「用户/日期」筛选变化,不随类型标签变化
        $base = $this->matchUser(BalanceLog::query(), $q)->dateBetween($from, $to);

        $logs = (clone $base)
            ->when(in_array($type, BalanceLog::types(), true), fn ($query) => $query->where('type', $type))
            ->with('user', 'order')->latest()->paginate(30)->withQueryString();

        return view('admin.finance.index', [
            'logs' => $logs,
            'type' => $type,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'sumRecharge' => (float) (clone $base)->where('type', 'recharge')->sum('amount'),
            'sumConsume' => abs((float) (clone $base)->where('type', 'consume')->sum('amount')),
            'sumRebate' => (float) (clone $base)->where('type', 'rebate')->sum('amount'),
            'sumBonus' => (float) (clone $base)->where('type', 'bonus')->sum('amount'),
            'counts' => [
                'all' => (clone $base)->count(),
                'recharge' => (clone $base)->where('type', 'recharge')->count(),
                'consume' => (clone $base)->where('type', 'consume')->count(),
                'rebate' => (clone $base)->where('type', 'rebate')->count(),
                'bonus' => (clone $base)->where('type', 'bonus')->count(),
                'adjust' => (clone $base)->where('type', 'adjust')->count(),
                'refund' => (clone $base)->where('type', 'refund')->count(),
            ],
        ]);
    }
}
