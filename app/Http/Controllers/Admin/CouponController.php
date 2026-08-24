<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return view('admin.coupons.index', ['coupons' => Coupon::latest()->get()]);
    }

    public function create()
    {
        return view('admin.coupons.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:coupons,code'],
            'note' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:percent,amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_use' => ['nullable', 'integer'],
            'expires_at' => ['nullable', 'date'],
            'show_on_checkout' => ['nullable', 'boolean'],
        ]);
        $data['max_use'] = $data['max_use'] ?? -1;
        $data['enabled'] = true;
        $data['show_on_checkout'] = (bool) ($data['show_on_checkout'] ?? false);
        Coupon::create($data);

        return redirect('/admin/coupons')->with('status', '优惠券已创建');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return redirect('/admin/coupons')->with('status', '优惠券已删除');
    }
}
