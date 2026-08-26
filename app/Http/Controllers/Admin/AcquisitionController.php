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

        // 每用户已支付订单营收：SQL 聚合(join 区间用户),不再把全表拉进内存
        $revByUser = Order::where('orders.status', 'paid')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->when($from, fn ($q) => $q->whereDate('users.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('users.created_at', '<=', $to))
            ->groupBy('orders.user_id')
            ->selectRaw('orders.user_id as uid, sum(orders.amount) as rev')->pluck('rev', 'uid');

        $channels = [];   // 渠道 => [reg, paid, revenue]
        $utmSource = [];
        $utmCampaign = [];
        $referers = [];
        $total = 0;
        $paidTotal = 0;
        $revenueTotal = 0.0;
        // 分批遍历,内存从"全表"降到每批 1000
        (clone $base)->select('id', 'ref_by', 'reg_referer', 'utm_source', 'utm_campaign')
            ->chunkById(1000, function ($chunk) use (&$channels, &$utmSource, &$utmCampaign, &$referers, &$total, &$paidTotal, &$revenueTotal, $revByUser) {
                foreach ($chunk as $u) {
                    $total++;
                    $ch = $this->channelOf($u);
                    $slot = $channels[$ch] ?? ['reg' => 0, 'paid' => 0, 'revenue' => 0.0];
                    $slot['reg']++;
                    $rev = (float) ($revByUser[$u->id] ?? 0);
                    if ($rev > 0) {
                        $slot['paid']++;
                        $slot['revenue'] += $rev;
                        $paidTotal++;
                        $revenueTotal += $rev;
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
            });

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

        $inviterRows = (clone $base)->whereNotNull('ref_by')
            ->selectRaw('ref_by, count(*) as cnt')->groupBy('ref_by')->orderByDesc('cnt')->take(5)->get();
        $inviterUsers = User::whereIn('id', $inviterRows->pluck('ref_by'))->get()->keyBy('id');   // 一次预取,避免 N+1
        $topInviters = $inviterRows->map(fn ($r) => ['user' => $inviterUsers->get($r->ref_by), 'cnt' => $r->cnt]);

        return view('admin.system.acquisition', [
            'total' => $total,
            'paidTotal' => $paidTotal,
            'revenueTotal' => $revenueTotal,
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
