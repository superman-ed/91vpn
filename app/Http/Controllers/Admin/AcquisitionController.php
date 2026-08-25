<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcquisitionController extends Controller
{
    /** GET /admin/system/acquisition —— 来路 / 获客统计(注册来源) */
    public function index(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $base = User::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $users = (clone $base)->get(['id', 'ref_by', 'reg_referer']);

        // 渠道分类：邀请 / 直接 / 外部来路(按 referer host)
        $channels = ['邀请注册' => 0, '直接访问' => 0];
        $referers = [];
        foreach ($users as $u) {
            if ($u->ref_by) {
                $channels['邀请注册']++;
            } elseif (empty($u->reg_referer)) {
                $channels['直接访问']++;
            } else {
                $host = parse_url($u->reg_referer, PHP_URL_HOST) ?: '其它来路';
                $referers[$host] = ($referers[$host] ?? 0) + 1;
            }
        }
        arsort($referers);

        // 邀请人 TOP（拉新最多）
        $topInviters = (clone $base)->whereNotNull('ref_by')
            ->selectRaw('ref_by, count(*) as cnt')->groupBy('ref_by')->orderByDesc('cnt')->take(5)->get()
            ->map(fn ($r) => ['user' => User::find($r->ref_by), 'cnt' => $r->cnt]);

        return view('admin.system.acquisition', [
            'total' => $users->count(),
            'channels' => collect($channels)->filter(),
            'referers' => collect($referers)->take(10),
            'topInviters' => $topInviters,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
