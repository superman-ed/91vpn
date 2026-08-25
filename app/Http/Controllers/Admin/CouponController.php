<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        return view('admin.coupons.index', ['coupons' => Coupon::latest()->get()]);
    }

    public function create()
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['enabled' => true])]);
    }

    public function store(Request $request)
    {
        $coupon = Coupon::create($this->validated($request));
        audit('coupon.create', "创建优惠券「{$coupon->code}」", $coupon);

        return redirect('/admin/coupons')->with('status', '优惠券已创建');
    }

    /** POST /admin/coupons/batch —— 批量生成同规则优惠券（活动发券） */
    public function batchStore(Request $request)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:500'],
            'prefix' => ['nullable', 'string', 'max:12', 'alpha_num'],
            'type' => ['required', 'in:percent,amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'periods' => ['nullable', 'array'],
            'periods.*' => ['in:month,quarter,half_year,year'],
            'max_use' => ['nullable', 'integer'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $prefix = strtoupper($data['prefix'] ?? '');
        $periods = ! empty($data['periods']) ? array_values($data['periods']) : null;

        $existing = Coupon::pluck('code')->flip();
        $rows = [];
        $seen = [];
        while (count($rows) < $data['count']) {
            $code = $prefix.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10 - min(4, strlen($prefix))));
            if (isset($existing[$code]) || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $rows[] = [
                'code' => $code, 'note' => '', 'type' => $data['type'], 'value' => $data['value'],
                'periods' => $periods ? json_encode($periods) : null,
                'max_use' => $data['max_use'] ?? -1, 'used' => 0,
                'expires_at' => $data['expires_at'] ?? null, 'enabled' => true, 'show_on_checkout' => false,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        Coupon::insert($rows);
        audit('coupon.create', "批量生成 {$data['count']} 张优惠券".($prefix ? "（前缀 {$prefix}）" : ''));

        return redirect('/admin/coupons')->with('status', "已批量生成 {$data['count']} 张优惠券");
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validated($request, $coupon));
        audit('coupon.update', "更新优惠券「{$coupon->code}」", $coupon);

        return redirect('/admin/coupons')->with('status', '优惠券已更新');
    }

    public function destroy(Coupon $coupon)
    {
        audit('coupon.delete', "删除优惠券「{$coupon->code}」", $coupon);
        $coupon->delete();

        return redirect('/admin/coupons')->with('status', '优惠券已删除');
    }

    /** 创建/编辑共用校验（编辑时 code 唯一忽略自身；缺省启用） */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'note' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:percent,amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'periods' => ['nullable', 'array'],
            'periods.*' => ['in:month,quarter,half_year,year'],
            'max_use' => ['nullable', 'integer'],
            'expires_at' => ['nullable', 'date'],
            'enabled' => ['nullable', 'boolean'],
            'show_on_checkout' => ['nullable', 'boolean'],
        ]);
        $data['periods'] = ! empty($data['periods']) ? array_values($data['periods']) : null;   // 空=不限周期
        $data['max_use'] = $data['max_use'] ?? -1;
        $data['enabled'] = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : true;
        $data['show_on_checkout'] = (bool) ($data['show_on_checkout'] ?? false);

        return $data;
    }
}
