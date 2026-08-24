<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BalanceLog;
use App\Models\Order;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /** GET /user/wallet */
    public function index()
    {
        $user = auth()->user();

        return view('user.wallet', [
            'user' => $user,
            'orders' => $user->orders()->with('plan')->latest()->get(),
            'balanceLogs' => $user->balanceLogs()->latest()->take(20)->get(),
            'totalRecharge' => (float) $user->balanceLogs()->where('type', 'recharge')->sum('amount'),
            'totalConsume' => abs((float) $user->balanceLogs()->where('type', 'consume')->sum('amount')),
            'totalRebate' => (float) \App\Models\Payback::where('user_id', $user->id)->sum('amount'),
        ]);
    }

    /** POST /user/wallet/recharge —— 模拟充值 */
    public function recharge(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:100000']]);

        $user = auth()->user();
        DB::transaction(function () use ($user, $data) {
            $user->increment('money', $data['amount']);
            BalanceLog::create([
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'type' => 'recharge',
                'balance_after' => $user->fresh()->money,
                'remark' => '模拟充值',
            ]);
        });

        return back()->with('status', "充值成功 ¥{$data['amount']}（模拟）");
    }

    /** POST /user/order/{order}/pay-balance —— 余额支付订单 */
    public function payBalance(Order $order, BillingService $billing)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->status !== 'pending') {
            return redirect('/user')->with('status', '订单状态异常');
        }

        $billing->payWithBalance($order);

        return redirect('/user')->with('status', '余额支付成功，套餐已到账！');
    }
}
