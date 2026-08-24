<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BillingService;
use App\Services\EpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /** 易支付异步回调：验签 → 校验金额 → 幂等发货，返回纯文本 success/fail */
    public function notify(Request $request, EpayService $epay, BillingService $billing)
    {
        $params = $request->all();

        if (! $epay->configured() || ! $epay->verify($params)) {
            return response('fail');
        }
        if (($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return response('fail');
        }

        $order = Order::find($params['out_trade_no'] ?? null);
        if (! $order) {
            return response('fail');
        }

        // 金额防篡改
        if (abs((float) ($params['money'] ?? 0) - (float) $order->amount) > 0.01) {
            return response('fail');
        }

        try {
            $billing->settleOrder($order, (string) ($params['type'] ?? 'epay'));   // 幂等：重复回调不会重复发货
        } catch (\Throwable $e) {
            // 已扣款但发货失败(如套餐售罄)：记录待人工处理，仍回 success 避免网关无限重试
            Log::warning('epay settle failed', ['order' => $order->id, 'err' => $e->getMessage()]);
        }

        return response('success');
    }

    /** 易支付同步跳转：用户支付后返回站内，发货以异步回调为准 */
    public function epayReturn()
    {
        return redirect('/user')->with('status', '支付完成，套餐到账后可在「我的钱包」查看订单状态。');
    }
}
