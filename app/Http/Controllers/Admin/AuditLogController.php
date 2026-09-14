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
        // `[!]` 定时任务写的动作也必须落在某个分组里,否则按分组筛时它们【看不见】。
        // SystemAuditTrailTest 有一条测试机械校验这件事。
        'recharge' => '充值',
    ];

    /** GET /admin/system/audit —— 管理员操作日志 */
    public function index(Request $request)
    {
        $group = $request->query('group');
        // 来源：human=人工操作 / system=定时任务。默认两者都看。
        //
        // `[!]` 判据是【动作名】而不是 admin_id 是否为空 —— 后者有两个来源，
        // 另一个是"管理员被删了"，那类记录仍然属于人工操作。
        $src = in_array($request->query('src'), ['human', 'system'], true) ? $request->query('src') : null;
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $base = AuditLog::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('description', 'like', "%{$q}%")
                ->orWhereHas('admin', fn ($a) => $a->where('email', 'like', "%{$q}%"))))
            ->when($group, fn ($query) => $query->where('action', 'like', "{$group}.%"))
            ->when($src === 'system', fn ($query) => $query
                ->whereNull('admin_id')->whereIn('action', array_keys(AuditLog::SYSTEM_ACTIONS)))
            ->when($src === 'human', fn ($query) => $query
                ->where(fn ($w) => $w->whereNotNull('admin_id')
                    ->orWhereNotIn('action', array_keys(AuditLog::SYSTEM_ACTIONS))))
            ->dateBetween($from, $to);

        return view('admin.system.audit', [
            'logs' => (clone $base)->with('admin')->latest()->paginate(30)->withQueryString(),
            'group' => $group,
            'src' => $src,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'total' => (clone $base)->count(),
            'todayCount' => (clone $base)->whereDate('created_at', today())->count(),
        ]);
    }
}
