<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\OrderService;
use App\Services\PlanCatalog;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(private OrderService $orders, private PlanCatalog $catalog) {}

    /** GET /user/shop */
    public function index()
    {
        // `[!]` 分组/时长逻辑统一走 PlanCatalog —— 与官网首页同源,价格权益不会各说各话。
        $onSale = $this->catalog->onSale();

        return view('user.shop', [
            'groups' => $this->catalog->groups($onSale),
            'dataPacks' => $this->catalog->dataPacks($onSale),
        ]);
    }

    /** POST /user/order/create —— 下单（生成 pending 订单，跳收银台结算） */
    public function createOrder(Request $request)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);

        $plan = Plan::findOrFail($data['plan_id']);
        $order = $this->orders->createPending(auth()->user(), $plan);

        return redirect("/user/order/{$order->id}");
    }

    /** GET /user/order/{order} —— 收银台结算页 */
    public function checkout(Order $order, BillingService $billing, \App\Services\EpayService $epay)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user/wallet')->with('status', '该订单已处理，无需支付');
        }

        $user = auth()->user();
        // 普通套餐且当前有生效套餐 → 本单支付后排队，预计生效时间
        $queuedActivateAt = null;
        if (! $order->plan->is_data_pack) {
            $end = $billing->effectiveEnd($user);
            if ($end->isFuture()) {
                $queuedActivateAt = $end;
            }
        }

        return view('user.checkout', [
            'order' => $order->load('plan', 'coupon'),
            'user' => $user,
            'couponNotes' => \App\Models\Coupon::checkoutVisible(),
            'queuedActivateAt' => $queuedActivateAt,
            // 在线支付是否可用:配了网关(生产跳转) 或 本地/测试(模拟直付)。都不满足则只留余额支付,不摆无效按钮
            'onlinePay' => $epay->configured() || app()->environment(['local', 'testing']),
        ]);
    }

    /** POST /user/order/{order}/cancel —— 取消待支付订单（行锁防与支付竞态） */
    public function cancelOrder(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $cancelled = \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return false;
            }
            $locked->update(['status' => 'cancelled']);

            return true;
        });

        abort_unless($cancelled, 403);

        return redirect('/user/wallet')->with('status', '订单已取消');
    }

    /** POST /user/order/{order}/coupon —— 收银台应用/移除优惠码（按原价重算，支付成功才计 used） */
    public function applyCoupon(Order $order, Request $request)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_if($order->status !== 'pending', 403);

        $data = $request->validate(['coupon' => ['nullable', 'string', 'max:32']]);

        $this->orders->applyCoupon($order, $data['coupon'] ?? null);

        $status = empty($data['coupon'])
            ? '已移除优惠码'
            : "优惠码已应用，应付 ¥{$order->fresh()->amount}";

        return redirect("/user/order/{$order->id}")->with('status', $status);
    }

    /** 在线支付渠道（无真实网关，均走模拟成功，记录渠道名） */
    private const ONLINE_METHODS = ['alipay' => '支付宝', 'wechat' => '微信支付', 'usdt' => 'USDT'];

    /** POST /user/order/{order}/pay —— 收银台统一支付：按所选方式发货 */
    public function pay(Order $order, Request $request, BillingService $billing, \App\Services\EpayService $epay)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_if($order->status !== 'pending', 403);

        // 0 元订单（优惠券抵满）直接发货，无需选支付方式
        if ((float) $order->amount <= 0) {
            $billing->settleOrder($order, 'free');

            return redirect('/user')->with('status', '订单已发货！');
        }

        $data = $request->validate([
            'method' => ['required', 'in:balance,'.implode(',', array_keys(self::ONLINE_METHODS))],
        ]);

        if ($data['method'] === 'balance') {
            $billing->payWithBalance($order);   // 锁内校验余额，不足会抛错误

            return redirect('/user')->with('status', '余额支付成功，套餐已到账！');
        }

        // 在线渠道：已配置网关则跳转支付，回调发货（未映射渠道跳网关收银台）
        if ($epay->configured()) {
            return redirect()->away($epay->payUrl($order, $data['method']));
        }

        // 未配置网关：仅开发/测试环境允许模拟直付；生产环境必须报错，严禁零成本到账
        if (! app()->environment(['local', 'testing'])) {
            return back()->with('status', '在线支付暂不可用，请稍后再试或联系客服。');
        }
        $billing->settleOrder($order, $data['method']);
        $channel = self::ONLINE_METHODS[$data['method']];

        return redirect('/user')->with('status', "{$channel}支付成功，套餐已到账！（未配置网关，模拟）");
    }

    /** POST /user/subscription/end —— 立即结束当前套餐（仅单月套餐，让排队套餐生效） */
    public function endSubscription(BillingService $billing)
    {
        $user = auth()->user();
        if (! $user->canEndCurrentPackage()) {
            return back()->with('status', '当前套餐不可立即结束（仅单月套餐可用）');
        }

        $billing->endCurrentPackage($user);
        $activated = $user->fresh()->hasActivePackage();

        return back()->with('status', $activated ? '当前套餐已结束，排队套餐已生效' : '当前套餐已结束');
    }

    /** POST /user/order/{order}/mock-pay —— 模拟支付并发货（仅开发环境） */
    public function mockPay(Order $order, BillingService $billing)
    {
        abort_unless(app()->environment('local', 'testing'), 404);
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user')->with('status', '订单状态异常，无需支付');
        }

        $billing->settleOrder($order, 'mock');

        return redirect('/user')->with('status', '支付成功，套餐已到账！');
    }
}
