<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $status = $request->query('status');   // member/free/expired/banned

        $base = User::where('is_admin', false);

        $users = (clone $base)
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->when($status, fn ($query) => $this->applyStatus($query, $status))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
            'status' => $status,
            'counts' => [
                'all' => (clone $base)->count(),
                'member' => $this->applyStatus(clone $base, 'member')->count(),
                'free' => $this->applyStatus(clone $base, 'free')->count(),
                'expired' => $this->applyStatus(clone $base, 'expired')->count(),
                'banned' => $this->applyStatus(clone $base, 'banned')->count(),
            ],
        ]);
    }

    private function applyStatus($query, string $status)
    {
        return match ($status) {
            'member' => $query->where('banned', false)->where('class', '>', 0)->where('class_expire', '>', now()),
            'free' => $query->where('banned', false)->where('class', 0),
            'expired' => $query->where('banned', false)->where('class', '>', 0)->where('class_expire', '<=', now()),
            'banned' => $query->where('banned', true),
            default => $query,
        };
    }

    public function edit(User $user)
    {
        abort_if($user->is_admin, 403, '管理员请在「管理员」页管理');

        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:32'],
            'class' => ['required', 'integer', 'min:0', 'max:9'],
            'transfer_enable_gb' => ['required', 'numeric', 'min:0'],
            'class_expire' => ['nullable', 'date'],
            'node_speed_limit' => ['nullable', 'integer', 'min:0'],
            'node_ip_limit' => ['nullable', 'integer', 'min:0'],
            'money' => ['nullable', 'numeric'],
        ]);

        $quota = (int) round($data['transfer_enable_gb'] * (1024 ** 3));
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'class' => $data['class'],
            'transfer_enable' => $quota,
            'base_transfer_enable' => $quota,   // 同步基准，避免月度重置归位回旧配额
            'class_expire' => $data['class_expire'] ?? $user->class_expire,
            'node_speed_limit' => $data['node_speed_limit'] ?? 0,
            'node_ip_limit' => $data['node_ip_limit'] ?? 0,
            'money' => $data['money'] ?? $user->money,
        ]);

        return redirect('/admin/users')->with('status', "已更新用户 {$user->email}");
    }

    public function toggleBan(User $user)
    {
        $user->update(['banned' => ! $user->banned]);

        return back()->with('status', $user->banned ? '已封禁' : '已解封');
    }

    /** 开通套餐：选套餐页 */
    public function grant(User $user)
    {
        abort_if($user->is_admin, 403);

        return view('admin.users.grant', [
            'user' => $user,
            'plans' => Plan::where('is_data_pack', false)
                ->orderBy('name')->orderByRaw("FIELD(period,'month','quarter','half_year','year')")->get(),
        ]);
    }

    /** 开通套餐：发货 + 记一条管理员订单 */
    public function doGrant(Request $request, User $user, BillingService $billing)
    {
        abort_if($user->is_admin, 403);
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $plan = Plan::findOrFail($data['plan_id']);
        abort_if($plan->is_data_pack, 422);

        $billing->deliver($user, $plan);
        Order::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 0,
            'status' => 'paid', 'period' => $plan->period, 'pay_method' => 'admin',
            'paid_at' => now(), 'delivered_at' => now(),
        ]);

        return redirect('/admin/users')->with('status', "已为 {$user->email} 开通「{$plan->name}」");
    }

    /** 重置已用流量(u/d 清零) */
    public function resetTraffic(User $user)
    {
        $user->update(['u' => 0, 'd' => 0]);

        return back()->with('status', "已重置 {$user->email} 的已用流量");
    }

    /** 重置登录密码 */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);
        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', "已重置 {$user->email} 的登录密码");
    }
}
