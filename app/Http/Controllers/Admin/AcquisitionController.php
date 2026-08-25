<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class AcquisitionController extends Controller
{
    /** GET /admin/system/acquisition —— 来路 / 获客统计（渠道 + UTM + 转化质量） */
    public function index(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $base = User::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $users = (clone $base)->get(['id', 'ref_by', 'reg_referer', 'utm_source', 'utm_medium', 'utm_campaign']);

        // 每用户已支付订单营收
        $revByUser = Order::where('status', 'paid')->whereIn('user_id', $users->pluck('id'))
            ->selectRaw('user_id, sum(amount) as rev')->groupBy('user_id')->pluck('rev', 'user_id');

        $channels = [];   // 渠道 => [reg, paid, revenue]
        $utmSource = [];
        $utmCampaign = [];
        $referers = [];
        foreach ($users as $u) {
            $ch = $this->channelOf($u);
            $slot = $channels[$ch] ?? ['reg' => 0, 'paid' => 0, 'revenue' => 0.0];
            $slot['reg']++;
            $rev = (float) ($revByUser[$u->id] ?? 0);
            if ($rev > 0) {
                $slot['paid']++;
                $slot['revenue'] += $rev;
            }
            $channels[$ch] = $slot;

            if ($u->utm_source) {
                $utmSource[$u->utm_source] = ($utmSource[$u->utm_source] ?? 0) + 1;
            }
            if ($u->utm_campaign) {
                $utmCampaign[$u->utm_campaign] = ($utmCampaign[$u->utm_campaign] ?? 0) + 1;
            }
            if (! $u->ref_by && ! $u->utm_source && $u->reg_referer) {
                $host = parse_url($u->reg_referer, PHP_URL_HOST) ?: '其它来路';
                $referers[$host] = ($referers[$host] ?? 0) + 1;
            }
        }

        // 转化质量：按营收降序
        $channelRows = collect($channels)->map(fn ($v, $k) => [
            'channel' => $k,
            'reg' => $v['reg'],
            'paid' => $v['paid'],
            'rate' => $v['reg'] > 0 ? round($v['paid'] / $v['reg'] * 100, 1) : 0,
            'revenue' => $v['revenue'],
        ])->sortByDesc('revenue')->values();

        arsort($utmSource);
        arsort($utmCampaign);
        arsort($referers);

        $topInviters = (clone $base)->whereNotNull('ref_by')
            ->selectRaw('ref_by, count(*) as cnt')->groupBy('ref_by')->orderByDesc('cnt')->take(5)->get()
            ->map(fn ($r) => ['user' => User::find($r->ref_by), 'cnt' => $r->cnt]);

        return view('admin.system.acquisition', [
            'total' => $users->count(),
            'paidTotal' => $users->filter(fn ($u) => ($revByUser[$u->id] ?? 0) > 0)->count(),
            'revenueTotal' => (float) $revByUser->sum(),
            'channelRows' => $channelRows,
            'utmSource' => collect($utmSource)->take(10),
            'utmCampaign' => collect($utmCampaign)->take(10),
            'referers' => collect($referers)->take(8),
            'topInviters' => $topInviters,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** 用户的获客渠道：UTM 来源 > 邀请 > 外部来路域名 > 直接 */
    private function channelOf(User $u): string
    {
        if ($u->utm_source) {
            return $u->utm_source;
        }
        if ($u->ref_by) {
            return '邀请';
        }
        if ($u->reg_referer) {
            return parse_url($u->reg_referer, PHP_URL_HOST) ?: '其它来路';
        }

        return '直接';
    }
}
