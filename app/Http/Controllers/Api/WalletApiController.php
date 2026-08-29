<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BalanceLog;
use App\Models\Payback;
use App\Models\Recharge;
use App\Services\BillingService;
use App\Services\EpayService;
use Illuminate\Http\Request;

class WalletApiController extends Controller
{
    /** GET /api/wallet —— 余额、累计统计、最近资金流水 */
    public function index(Request $request)
    {
        $user = $request->user();

        $records = $user->balanceLogs()->latest()->limit(30)->get()->map(fn (BalanceLog $l) => [
            'amount' => (float) $l->amount,
            'type' => $l->type,
            'remark' => $l->remark,
            'balance_after' => (float) $l->balance_after,
            'created_at' => $l->created_at?->toDateTimeString(),
        ])->values();

        return response()->json(['ret' => 1, 'data' => [
            'balance' => (float) $user->money,
            'totals' => [
                'recharge' => (float) $user->balanceLogs()->where('type', 'recharge')->sum('amount'),
                'consume' => abs((float) $user->balanceLogs()->where('type', 'consume')->sum('amount')),
                'rebate' => (float) Payback::where('user_id', $user->id)->sum('amount'),
            ],
            'records' => $records,
        ]]);
    }

    /** POST /api/wallet/recharge —— 充值:配了网关返回收银台 URL,否则本地/测试模拟到账 */
    public function recharge(Request $request, BillingService $billing, EpayService $epay)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:100000']]);
        $user = $request->user();
        $amount = (float) $data['amount'];

        // 配了网关:建 pending 充值单,返回易支付收银台 URL,由 App 打开;回调到账
        if ($epay->configured()) {
            $recharge = Recharge::create(['user_id' => $user->id, 'amount' => $amount, 'status' => 'pending']);

            return response()->json(['ret' => 1, 'data' => [
                'status' => 'redirect',
                'pay_url' => $epay->buildUrl($recharge->order_no, '钱包充值', $amount),
            ]]);
        }

        // 未配网关:仅本地/测试允许模拟到账;生产必须报错,严禁零成本充值
        if (! app()->environment(['local', 'testing'])) {
            return response()->json(['ret' => 0, 'msg' => '充值暂不可用，请稍后再试或联系客服'], 503);
        }

        $billing->applyRecharge($user, $amount, null, '模拟充值');

        return response()->json(['ret' => 1, 'data' => [
            'status' => 'credited',
            'balance' => (float) $user->fresh()->money,
        ], 'msg' => '充值成功（模拟）']);
    }
}
