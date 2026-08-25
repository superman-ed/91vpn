<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PromoChannel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromoController extends Controller
{
    /** GET /admin/promo —— 推广代理列表 + 业绩 */
    public function index()
    {
        $channels = PromoChannel::orderByDesc('id')->get();

        // 各推广码业绩：注册数 / 付费用户 / 营收
        $reg = User::whereNotNull('promo_code')->selectRaw('promo_code, count(*) as c')->groupBy('promo_code')->pluck('c', 'promo_code');
        $paidRows = Order::where('orders.status', 'paid')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereNotNull('users.promo_code')
            ->selectRaw('users.promo_code as code, count(distinct users.id) as paid_users, sum(orders.amount) as revenue')
            ->groupBy('users.promo_code')->get()->keyBy('code');

        $stats = $channels->mapWithKeys(function ($ch) use ($reg, $paidRows) {
            $regCount = (int) ($reg[$ch->code] ?? 0);
            $p = $paidRows->get($ch->code);
            return [$ch->code => [
                'reg' => $regCount,
                'paid' => (int) ($p->paid_users ?? 0),
                'rate' => $regCount > 0 ? round((int) ($p->paid_users ?? 0) / $regCount * 100, 1) : 0,
                'revenue' => (float) ($p->revenue ?? 0),
            ]];
        });

        return view('admin.promo.index', ['channels' => $channels, 'stats' => $stats]);
    }

    /** POST /admin/promo —— 创建推广码 */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', 'alpha_dash', 'unique:promo_channels,code'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $code = $data['code'] ? Str::upper($data['code']) : $this->genCode();

        $channel = PromoChannel::create([
            'code' => $code, 'name' => $data['name'], 'note' => $data['note'] ?? '', 'enabled' => true,
        ]);
        audit('promo.create', "创建推广码「{$channel->name}」({$code})", $channel);

        return back()->with('status', "推广码 {$code} 已创建");
    }

    /** PUT /admin/promo/{channel} —— 编辑 / 启停 */
    public function update(Request $request, PromoChannel $channel)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        $channel->update([
            'name' => $data['name'], 'note' => $data['note'] ?? '',
            'enabled' => $request->boolean('enabled'),
        ]);
        audit('promo.update', "更新推广码「{$channel->name}」({$channel->code})", $channel);

        return back()->with('status', '已保存');
    }

    /** DELETE /admin/promo/{channel} */
    public function destroy(PromoChannel $channel)
    {
        audit('promo.delete', "删除推广码「{$channel->name}」({$channel->code})", $channel);
        $channel->delete();   // 已注册用户的 promo_code 仍保留归因

        return back()->with('status', '已删除（历史归因保留）');
    }

    /** GET /admin/promo/{channel} —— 该代理带来的用户明细 */
    public function show(PromoChannel $channel)
    {
        $users = User::where('promo_code', $channel->code)
            ->withSum(['orders as paid_amount' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->orderByDesc('id')->paginate(30);

        return view('admin.promo.show', ['channel' => $channel, 'users' => $users]);
    }

    private function genCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (PromoChannel::where('code', $code)->exists());

        return $code;
    }
}
