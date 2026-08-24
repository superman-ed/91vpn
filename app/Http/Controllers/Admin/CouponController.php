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
        Coupon::create($this->validated($request));

        return redirect('/admin/coupons')->with('status', '优惠券已创建');
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validated($request, $coupon));

        return redirect('/admin/coupons')->with('status', '优惠券已更新');
    }

    public function destroy(Coupon $coupon)
    {
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
            'max_use' => ['nullable', 'integer'],
            'expires_at' => ['nullable', 'date'],
            'enabled' => ['nullable', 'boolean'],
            'show_on_checkout' => ['nullable', 'boolean'],
        ]);
        $data['max_use'] = $data['max_use'] ?? -1;
        $data['enabled'] = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : true;
        $data['show_on_checkout'] = (bool) ($data['show_on_checkout'] ?? false);

        return $data;
    }
}
