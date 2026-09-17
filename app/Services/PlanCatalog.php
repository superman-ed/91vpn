<?php

namespace App\Services;

use App\Models\Plan;

/**
 * 在售套餐目录:同名套餐归组 + 组内按 1/3/6/12 月排时长。
 *
 * [!] 单一事实来源:商店页(ShopController)与官网首页(HomeController)都用它,
 *   避免"营销页和购买页各算各的、价格/权益对不上"。
 */
class PlanCatalog
{
    public const PERIOD_LABELS = ['month' => '1月', 'quarter' => '3月', 'half_year' => '6月', 'year' => '12月'];

    public const PERIOD_MONTHS = ['month' => 1, 'quarter' => 3, 'half_year' => 6, 'year' => 12];

    /** @return \Illuminate\Support\Collection<int,Plan> 在售套餐(含加油包) */
    public function onSale()
    {
        return Plan::where('on_sale', true)->orderBy('sort')->get();
    }

    /** 流量包(加油包)单独成区。 */
    public function dataPacks($onSale = null)
    {
        return ($onSale ?? $this->onSale())->where('is_data_pack', true)->map(fn (Plan $p) => [
            'plan_id' => $p->id,
            'name' => $p->name,
            'transfer_gb' => (int) $p->transfer_gb,
            'price' => self::money($p->price),
            'stock' => $p->stock,
            'sold_out' => $p->stock === 0,
        ])->values();
    }

    /** 普通套餐同名归组,组内按 1/3/6/12 月排各时长。 */
    public function groups($onSale = null)
    {
        return ($onSale ?? $this->onSale())->where('is_data_pack', false)
            ->groupBy('name')
            ->map(function ($rows) {
                $gb = (int) $rows->first()->transfer_gb;
                $durations = collect(self::PERIOD_LABELS)
                    ->map(function ($label, $period) use ($rows, $gb) {
                        $row = $rows->firstWhere('period', $period);
                        if (! $row) {
                            return null;
                        }
                        $months = self::PERIOD_MONTHS[$period];

                        return [
                            'plan_id' => $row->id,
                            'label' => $label,
                            'price' => self::money($row->price),
                            'days' => $row->duration_days,
                            'months' => $months,
                            'monthly_reset' => $row->resetsMonthly(),
                            'total_gb' => $row->resetsMonthly() ? $gb * $months : $gb,
                            'stock' => $row->stock,
                            'sold_out' => $row->stock === 0,
                        ];
                    })
                    ->filter()->values();

                return ['benefits' => $rows->first(), 'durations' => $durations];
            })
            ->filter(fn ($g) => $g['durations']->isNotEmpty())
            ->values();
    }

    /** 时长的流量文案(与商店页一致)。 */
    public function trafficText(array $d): string
    {
        $cn = [1 => '一', 3 => '三', 6 => '六', 12 => '十二'];
        if (! $d['monthly_reset']) {
            return $d['days'].'天总计 '.$d['total_gb'].'GB 流量（不重置）';
        }

        return $d['months'] <= 1
            ? '每月 '.$d['total_gb'].'GB 流量'
            : ($cn[$d['months']] ?? $d['months']).'个月总计 '.$d['total_gb'].'GB 流量';
    }

    private static function money($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    }
}
