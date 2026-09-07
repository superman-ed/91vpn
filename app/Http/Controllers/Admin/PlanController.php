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
        $plans = Plan::orderBy('sort')->orderBy('name')
            ->orderByRaw("FIELD(period,'month','quarter','half_year','year')")
            ->get();

        return view('admin.plans.index', ['plans' => $plans]);
    }

    /** POST /admin/plans/{plan}/toggle-sale —— 一键上架/下架 */
    public function toggleSale(Plan $plan)
    {
        $plan->update(['on_sale' => ! $plan->on_sale]);
        audit('plan.update', ($plan->on_sale ? '上架' : '下架')."套餐「{$plan->name}」", $plan);

        return back()->with('status', $plan->on_sale ? '已上架' : '已下架');
    }

    /** POST /admin/plans/{plan}/move —— 排序上移/下移 */
    public function move(Request $request, Plan $plan)
    {
        // 健壮做法:取当前显示顺序(sort,id)全表 → 与相邻项交换位置 → 整表重写为 0,1,2… 连续 sort。
        // 避免原"交换 sort 值"在批量建套餐共用同一 sort 时退化(跳多行/拆散组),并保证 sort 连续唯一。
        $dir = $request->input('dir') === 'up' ? 'up' : 'down';
        \DB::transaction(function () use ($plan, $dir) {
            $plans = Plan::orderBy('sort')->orderBy('id')->lockForUpdate()->get();
            $i = $plans->search(fn ($p) => $p->id === $plan->id);
            if ($i === false) {
                return;
            }
            $j = $dir === 'up' ? $i - 1 : $i + 1;
            if ($j < 0 || $j >= $plans->count()) {
                return; // 已在顶/底
            }
            $ordered = $plans->all();
            [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];
            foreach ($ordered as $pos => $p) {
                if ($p->sort !== $pos) {
                    $p->update(['sort' => $pos]);
                }
            }
            audit('plan.update', ($dir === 'up' ? '上移' : '下移')."套餐「{$plan->name}」排序", $plan);
        });

        return back();
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
            'reset_type' => ['nullable', 'in:monthly,none'],
            'class' => ['required', 'integer', 'min:0', 'max:9'],
            'speed_limit' => ['nullable', 'integer', 'min:0'],
            'ip_limit' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'integer'],
            'prices' => ['required', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0'], // 各档价格校验(与 update 对齐,防负价/垃圾值)
        ]);

        // 流量包：单件，仅取“1个月”价格，立即生效不排队
        if ($request->boolean('is_data_pack')) {
            $price = $data['prices']['month'] ?? null;
            if ($price === null || $price === '') {
                return back()->withErrors(['prices' => '流量包请在「1个月」那格填写价格'])->withInput();
            }
            Plan::create([
                'name' => $data['name'], 'price' => $price, 'period' => 'month', 'duration_days' => 0,
                'transfer_gb' => $data['transfer_gb'], 'is_data_pack' => true, 'reset_type' => 'monthly',
                'class' => 0, 'speed_limit' => 0, 'ip_limit' => 0,
                'sort' => $data['sort'] ?? 0, 'on_sale' => $request->boolean('on_sale'),
            ]);

            audit('plan.create', "创建流量包「{$data['name']}」");

            return redirect('/admin/plans')->with('status', '流量包已创建');
        }

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
                'reset_type' => $data['reset_type'] ?? 'monthly',
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

        audit('plan.create', "创建套餐「{$data['name']}」（{$created} 个时长）");

        return redirect('/admin/plans')->with('status', "已创建 {$created} 个时长套餐");
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request));
        audit('plan.update', "更新套餐「{$plan->name}」", $plan);
        return redirect('/admin/plans')->with('status', '套餐已更新');
    }

    public function destroy(Plan $plan)
    {
        // orders.plan_id 是 cascadeOnDelete 且无软删:直接删会连带物理抹除该套餐所有订单(含已支付)→ 财务凭据丢失。
        // 有成交记录的套餐不允许删除,引导改用「下架」(toggleSale)。
        if (\App\Models\Order::where('plan_id', $plan->id)->exists()) {
            return redirect('/admin/plans')->with('status', '该套餐已有订单记录,不能删除(会连带删除历史订单)。请改用「下架」。');
        }
        audit('plan.delete', "删除套餐「{$plan->name}」", $plan);
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
            'reset_type' => ['nullable', 'in:monthly,none'],
            'class' => ['required', 'integer', 'min:0', 'max:9'],
            'speed_limit' => ['nullable', 'integer', 'min:0'],
            'ip_limit' => ['nullable', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'sort' => ['nullable', 'integer'],
        ]);
        $data['on_sale'] = $request->boolean('on_sale');
        $data['is_data_pack'] = $request->boolean('is_data_pack');
        $data['reset_type'] ??= 'monthly';
        $data['speed_limit'] ??= 0;
        $data['ip_limit'] ??= 0;
        return $data;
    }
}
