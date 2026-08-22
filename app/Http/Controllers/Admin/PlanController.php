<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', ['plans' => Plan::orderBy('sort')->get()]);
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan(['period' => 'month', 'duration_days' => 30])]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validated($request));
        return redirect('/admin/plans')->with('status', '套餐已创建');
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
