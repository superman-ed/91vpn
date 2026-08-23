<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Services\BillingService;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /** GET /user/shop */
    public function index()
    {
        $periodLabels = ['month' => '1月', 'quarter' => '3月', 'half_year' => '6月', 'year' => '12月'];

        // 同名套餐归为一组，组内按 1/3/6/12 月排列各时长价格
        $groups = Plan::where('on_sale', true)->orderBy('sort')->get()
            ->groupBy('name')
            ->map(function ($rows) use ($periodLabels) {
                $durations = collect($periodLabels)
                    ->map(function ($label, $period) use ($rows) {
                        $row = $rows->firstWhere('period', $period);
                        if (! $row) {
                            return null;
                        }

                        return [
                            'plan_id' => $row->id,
                            'label' => $label,
                            'price' => rtrim(rtrim(number_format($row->price, 2), '0'), '.'),
                            'days' => $row->duration_days,
                            'stock' => $row->stock,
                            'sold_out' => $row->stock === 0,
                        ];
                    })
                    ->filter()->values();

                return ['benefits' => $rows->first(), 'durations' => $durations];
            })
            ->filter(fn ($g) => $g['durations']->isNotEmpty())
            ->values();

        return view('user.shop', ['groups' => $groups]);
    }

    /** POST /user/order/create —— 下单（生成 pending 订单，支持优惠券） */
    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'coupon' => ['nullable', 'string', 'max:32'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        if (! $plan->on_sale || $plan->stock === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plan_id' => '该套餐已售罄或已下架']);
        }

        $amount = (float) $plan->price;

        // 优惠券（选填）
        if (! empty($data['coupon'])) {
            $coupon = \App\Models\Coupon::where('code', $data['coupon'])->first();
            if (! $coupon || ! $coupon->isUsable()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['coupon' => '优惠券无效或已过期']);
            }
            $amount = $coupon->apply($amount);
            $coupon->increment('used');
        }

        $order = Order::create([
            'user_id' => auth()->id(),
            'plan_id' => $plan->id,
            'amount' => $amount,
            'status' => 'pending',
            'period' => $plan->period,
        ]);

        return redirect('/user/wallet')->with('status', "订单已创建：{$plan->name} ¥{$amount}，请在下方完成支付");
    }

    /** POST /user/order/{order}/mock-pay —— 模拟支付并发货 */
    public function mockPay(Order $order, BillingService $billing)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user')->with('status', '订单状态异常，无需支付');
        }

        $billing->completeOrder($order, 'mock');

        return redirect('/user')->with('status', '支付成功，套餐已到账！');
    }
}
