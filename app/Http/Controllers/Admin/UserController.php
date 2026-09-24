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

        $base = User::query();   // 含管理员(管理员也是用户)

        $users = (clone $base)
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('username', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
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

    /** GET /admin/users/export —— 按当前筛选导出用户 CSV */
    public function export(Request $request)
    {
        $q = $request->query('q');
        $status = $request->query('status');

        $query = User::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('username', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->when($status, fn ($query) => $this->applyStatus($query, $status));

        $header = ['ID', '账户名', '昵称', '身份', '等级', '状态', '已用(GB)', '配额(GB)', '余额', '到期时间', '注册时间'];
        $rows = (function () use ($query) {
            foreach ($query->orderByDesc('id')->cursor() as $u) {
                $state = $u->banned ? '已封禁' : ($u->class > 0 ? ($u->class_expire > now() ? '会员' : '已过期') : '免费');
                yield [
                    $u->id,
                    $u->ident(),
                    $u->name,
                    $u->is_admin ? '管理员' : '用户',
                    $u->class,
                    $state,
                    number_format(bytes_to_gb((int) ($u->u + $u->d)), 2),
                    number_format(bytes_to_gb((int) $u->transfer_enable), 2),
                    number_format((float) $u->money, 2),
                    $u->class_expire?->format('Y-m-d H:i'),
                    $u->created_at?->format('Y-m-d H:i:s'),
                ];
            }
        })();

        audit('user.export', '导出用户 CSV');

        return csv_download('users_'.now()->format('Ymd_His').'.csv', $header, $rows);
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
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user, BillingService $billing)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:32'],
            'class' => ['required', 'integer', 'min:0', 'max:9'],
            'transfer_enable_gb' => ['required', 'numeric', 'min:0'],
            'class_expire' => ['nullable', 'date'],
            'node_speed_limit' => ['nullable', 'integer', 'min:0'],
            'node_ip_limit' => ['nullable', 'integer', 'min:0'],
            'money' => ['nullable', 'numeric'],
            'original_money' => ['nullable', 'numeric'], // 打开编辑页时的余额快照,用于判断管理员是否真的改了余额
        ]);

        $quota = (int) round($data['transfer_enable_gb'] * (1024 ** 3));
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'class' => $data['class'],
            'transfer_enable' => $quota,
            'base_transfer_enable' => $quota,   // 同步基准，避免月度重置归位回旧配额
            'class_expire' => array_key_exists('class_expire', $data) ? $data['class_expire'] : $user->class_expire,   // 允许清空(置 null)
            'node_speed_limit' => $data['node_speed_limit'] ?? 0,
            'node_ip_limit' => $data['node_ip_limit'] ?? 0,
        ]);

        // 余额变动走调账入口(设为绝对值,delta 在锁内按当前 DB 值算)。
        // 只在管理员"确实改了余额"时才调账:与打开页时的快照 original_money 相等则跳过,
        // 避免存别的字段时把期间用户已充值/消费的余额覆盖回旧值(真金白银丢失)。
        $moneyChanged = array_key_exists('money', $data) && $data['money'] !== null
            && (! array_key_exists('original_money', $data) || $data['original_money'] === null
                || (float) $data['money'] !== (float) $data['original_money']);
        if ($moneyChanged) {
            $billing->adminAdjust($user, (float) $data['money'], auth()->user()->ident());
        }

        audit('user.update', "更新用户 {$user->ident()}", $user);

        return redirect('/admin/users')->with('status', "已更新用户 {$user->ident()}");
    }

    public function toggleBan(User $user)
    {
        if ($user->is_admin) {
            return back()->with('status', '管理员账号不可封禁，请到「管理员」页撤销其管理员权限后再操作');
        }
        $user->update(['banned' => ! $user->banned]);
        audit('user.ban', ($user->banned ? '封禁' : '解封')."用户 {$user->ident()}", $user);

        return back()->with('status', $user->banned ? '已封禁' : '已解封');
    }

    /** 开通套餐：选套餐页 */
    public function grant(User $user)
    {
        return view('admin.users.grant', [
            'user' => $user,
            'plans' => Plan::where('is_data_pack', false)
                ->orderBy('name')->orderByRaw("FIELD(period,'month','quarter','half_year','year')")->get(),
        ]);
    }

    /** 开通套餐：发货 + 记一条管理员订单 */
    public function doGrant(Request $request, User $user, BillingService $billing)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $plan = Plan::findOrFail($data['plan_id']);
        abort_if($plan->is_data_pack, 422);

        $billing->deliver($user, $plan);
        Order::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'amount' => 0,
            'status' => 'paid', 'period' => $plan->period, 'pay_method' => 'admin',
            'paid_at' => now(), 'delivered_at' => now(),
        ]);

        audit('user.grant', "为 {$user->ident()} 开通「{$plan->name}」", $user);

        return redirect('/admin/users')->with('status', "已为 {$user->ident()} 开通「{$plan->name}」");
    }

    /** 重置已用流量(u/d 清零) */
    public function resetTraffic(User $user)
    {
        $user->update(['u' => 0, 'd' => 0]);
        audit('user.reset_traffic', "重置 {$user->ident()} 已用流量", $user);

        return back()->with('status', "已重置 {$user->ident()} 的已用流量");
    }

    /**
     * 重置登录密码 —— 同时吊销该账号的全部登录凭据。
     *
     * `[!!]` 只改 password 是【没有用的】。客户端 API 认的是
     * DeviceToken.token 或 users.api_token(见 ClientToken 中间件),两者与密码
     * 【完全无关】且是长效的 —— 2026-09-24 实测:改完密码之后,
     * 原有的设备 token、账号 token、订阅链接【三者全部仍然可用】。
     * 而管理员按下这个按钮的场景几乎只有两个:用户丢了密码,或者账号被盗。
     * 后者正是要切断已经拿到凭据的人。
     *
     * `[!!]` 但要说清它【切不断什么】:代理访问靠的是 uuid,不是这些 token。
     * 账号被盗时正确的动作是【封禁】(NodeUserService 筛 banned=false,
     * 下一个轮询周期内该用户会从所有节点的用户名单里消失)或让用户重置 UUID。
     * 本方法只负责"登录凭据"这一层 —— 不把这一点写出来,修完仍是虚假的安全感。
     *
     * `[!]` 订阅 token(invite_token)刻意【不动】:换掉它会让用户必须重新导入
     * 订阅,那是独立的、用户自己有入口的动作(/user/node/reset-sub),
     * 不该被一次密码重置顺带触发。
     */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);

        $revoked = \App\Models\DeviceToken::where('user_id', $user->id)->count();
        $user->update([
            'password' => Hash::make($data['password']),
            'api_token' => \Illuminate\Support\Str::random(60),   // 账号级长效 token 一并换掉
        ]);
        \App\Models\DeviceToken::where('user_id', $user->id)->delete();

        audit('user.reset_password',
            "重置 {$user->ident()} 登录密码，并吊销其全部登录凭据（设备 token {$revoked} 个 + 账号 token）", $user);

        return back()->with('status',
            "已重置 {$user->ident()} 的登录密码，并吊销 {$revoked} 个已登录设备。"
            .'注意：这不影响代理连接（那靠 UUID）——账号被盗请改用「封禁」');
    }
}
