<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** 动作分组(过滤标签) */
    public const GROUPS = [
        'user' => '用户',
        'order' => '订单',
        'node' => '节点',
        'plan' => '套餐',
        'coupon' => '优惠券',
        'announcement' => '公告',
        'admin' => '管理员',
        'ticket' => '工单',
        'promo' => '推广',
        'setting' => '设置',
    ];

    /** GET /admin/system/audit —— 管理员操作日志 */
    public function index(Request $request)
    {
        $group = $request->query('group');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $base = AuditLog::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('description', 'like', "%{$q}%")
                ->orWhereHas('admin', fn ($a) => $a->where('email', 'like', "%{$q}%"))))
            ->when($group, fn ($query) => $query->where('action', 'like', "{$group}.%"))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to));

        return view('admin.system.audit', [
            'logs' => (clone $base)->with('admin')->latest()->paginate(30)->withQueryString(),
            'group' => $group,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'total' => (clone $base)->count(),
            'todayCount' => (clone $base)->whereDate('created_at', today())->count(),
        ]);
    }
}
