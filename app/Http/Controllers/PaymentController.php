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

        $outTradeNo = $params['out_trade_no'] ?? '';
        $money = (float) ($params['money'] ?? 0);
        $tradeNo = $params['trade_no'] ?? null;

        // 充值单以 R 开头,其余为套餐订单
        if (str_starts_with($outTradeNo, 'R')) {
            $recharge = \App\Models\Recharge::where('order_no', $outTradeNo)->first();
            if (! $recharge || abs($money - (float) $recharge->amount) > 0.01) {
                return response('fail');
            }
            try {
                $billing->creditRecharge($recharge, $tradeNo);   // 幂等
            } catch (\Throwable $e) {
                Log::warning('epay recharge failed', ['recharge' => $recharge->id, 'err' => $e->getMessage()]);
            }

            return response('success');
        }

        $order = Order::where('order_no', $outTradeNo)->first();
        if (! $order) {
            return response('fail');
        }
        if (abs($money - (float) $order->amount) > 0.01) {   // 金额防篡改
            return response('fail');
        }
        if (! empty($tradeNo)) {
            $order->update(['trade_no' => $tradeNo]);
        }

        try {
            $billing->settleOrder($order, (string) ($params['type'] ?? 'epay'));   // 幂等：重复回调不会重复发货
        } catch (\Throwable $e) {
            Log::warning('epay settle failed', ['order' => $order->id, 'err' => $e->getMessage()]);
        }

        return response('success');
    }

    /** 易支付同步跳转：用户支付后返回站内，发货以异步回调为准 */
    public function epayReturn()
    {
        return redirect('/user')->with('status', '支付完成，到账后可在「我的钱包」查看订单/余额。');
    }
}
