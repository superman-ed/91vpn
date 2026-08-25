<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceLog;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    private const TYPES = ['recharge', 'consume', 'rebate', 'bonus', 'adjust'];

    public function index(Request $request)
    {
        $type = $request->query('type');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        // 合计随「用户/日期」筛选变化,不随类型标签变化
        $base = BalanceLog::query()
            ->when($q, fn ($query) => $query->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to));

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
