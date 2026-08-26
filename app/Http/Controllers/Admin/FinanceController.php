<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceLog;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    private const TYPES = ['recharge', 'consume', 'rebate', 'bonus', 'adjust'];

    private const TYPE_NAME = ['recharge' => '充值', 'consume' => '消费', 'rebate' => '返佣', 'bonus' => '注册奖励', 'adjust' => '调账'];

    /** GET /admin/finance/export —— 按当前筛选导出资金流水 CSV */
    public function export(Request $request)
    {
        $type = $request->query('type');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = BalanceLog::query()
            ->when($q, fn ($b) => $b->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")))
            ->when(in_array($type, self::TYPES, true), fn ($b) => $b->where('type', $type))
            ->dateBetween($from, $to);

        $header = ['时间', '用户', '类型', '变动', '变动后余额', '关联订单', '交易号', '备注'];
        $rows = (function () use ($query) {
            foreach ($query->with('user', 'order')->latest()->cursor() as $l) {
                yield [
                    $l->created_at?->format('Y-m-d H:i:s'),
                    $l->user?->email ?? '—',
                    self::TYPE_NAME[$l->type] ?? $l->type,
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
        $base = BalanceLog::query()
            ->when($q, fn ($query) => $query->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")))
            ->dateBetween($from, $to);

        $logs = (clone $base)
            ->when(in_array($type, self::TYPES, true), fn ($query) => $query->where('type', $type))
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
            ],
        ]);
    }
}
