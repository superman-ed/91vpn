<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /** 各周期默认时长（天） */
    private const PERIOD_DAYS = ['month' => 30, 'quarter' => 90, 'half_year' => 180, 'year' => 365];

    public function index()
    {
        $plans = Plan::orderBy('name')
            ->orderByRaw("FIELD(period,'month','quarter','half_year','year')")
            ->get();

        return view('admin.plans.index', ['plans' => $plans]);
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    /** 批量创建：一次填权益 + 四档价格，为每个填了价格的时长各建一行同名套餐 */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'transfer_gb' => ['required', 'integer', 'min:0'],
            'class' => ['required', 'integer', 'min:0', 'max:9'],
            'speed_limit' => ['nullable', 'integer', 'min:0'],
            'ip_limit' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'integer'],
            'prices' => ['required', 'array'],
        ]);

        $created = 0;
        foreach (self::PERIOD_DAYS as $period => $days) {
            $price = $data['prices'][$period] ?? null;
            if ($price === null || $price === '') {
                continue;
            }

            Plan::create([
                'name' => $data['name'],
                'price' => $price,
                'period' => $period,
                'duration_days' => $days,
                'transfer_gb' => $data['transfer_gb'],
                'class' => $data['class'],
                'speed_limit' => $data['speed_limit'] ?? 0,
                'ip_limit' => $data['ip_limit'] ?? 0,
                'sort' => $data['sort'] ?? 0,
                'on_sale' => $request->boolean('on_sale'),
            ]);
            $created++;
        }

        if ($created === 0) {
            return back()->withErrors(['prices' => '至少填写一个时长的价格'])->withInput();
        }

        return redirect('/admin/plans')->with('status', "已创建 {$created} 个时长套餐");
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request));
        return redirect('/admin/plans')->with('status', '套餐已更新');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return redirect('/admin/plans')->with('status', '套餐已删除');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'period' => ['required', 'string'],
            'transfer_gb' => ['required', 'integer', 'min:0'],
            'class' => ['required', 'integer', 'min:0', 'max:9'],
            'speed_limit' => ['nullable', 'integer', 'min:0'],
            'ip_limit' => ['nullable', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'sort' => ['nullable', 'integer'],
        ]);
        $data['on_sale'] = $request->boolean('on_sale');
        $data['speed_limit'] ??= 0;
        $data['ip_limit'] ??= 0;
        return $data;
    }
}
