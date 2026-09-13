<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.admins.index', [
            'admins' => User::where('is_admin', true)->orderBy('id')->get(),
            'roles' => AdminAccess::ROLES,
            'matrix' => AdminAccess::MATRIX,
            'caps' => AdminAccess::CAPS,
        ]);
    }

    public function create()
    {
        return view('admin.admins.create', ['roles' => AdminAccess::ROLES]);
    }

    /** 添加管理员：可新建账号，或把已有账户名提升为管理员(后台按账户名登录) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{4,20}$/'],
            'name' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8'],
            'admin_role' => ['required', 'string', 'in:'.implode(',', array_keys(AdminAccess::ROLES))],
        ], [], ['username' => '账户名', 'admin_role' => '角色']);

        $existing = User::where('username', $data['username'])->first();

        if ($existing) {
            $existing->update(['is_admin' => true, 'admin_role' => $data['admin_role']]);
            $label = AdminAccess::ROLES[$data['admin_role']];
            audit('admin.grant', "将 {$existing->username} 提升为管理员（{$label}）", $existing);

            return redirect('/admin/admins')->with('status', "已将 {$existing->username} 设为{$label}");
        }

        if (empty($data['password'])) {
            throw ValidationException::withMessages(['password' => '新建管理员账号需设置密码（至少 8 位）']);
        }

        User::create([
            'username' => $data['username'],
            'name' => $data['name'] ?: '管理员',
            'password' => Hash::make($data['password']),
            'uuid' => (string) Str::uuid(),
            'passwd' => Str::lower(Str::random(6)),
            'ref_code' => Str::upper(Str::random(8)),
            'invite_token' => Str::random(32),
            'api_token' => Str::random(60),
            'class' => 0,
            'class_expire' => now(),
            'is_admin' => true,
            'admin_role' => $data['admin_role'],
        ]);
        $label = AdminAccess::ROLES[$data['admin_role']];
        audit('admin.create', "新建管理员账号 {$data['username']}（{$label}）");

        return redirect('/admin/admins')->with('status', "{$label} {$data['username']} 已创建");
    }

    /**
     * 改一个管理员的角色。
     *
     * `[!!]` 两条硬性保护，都是为了防【把自己或所有人锁在门外】：
     *   一、不能改自己的角色。想降级自己，让另一个超管来做 ——
     *       否则一次手滑就把唯一的超管变成只读审计，而改回去需要超管。
     *   二、必须留至少一个超管。
     * 这类错误的特点是【发生之后没有人能修】，所以要在发生之前拦。
     */
    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'admin_role' => ['required', 'string', 'in:'.implode(',', array_keys(AdminAccess::ROLES))],
        ], [], ['admin_role' => '角色']);

        if ($user->id === auth()->id()) {
            return back()->with('status', '不能改自己的角色 —— 请让另一位超级管理员操作');
        }
        if ($user->admin_role === 'super' && $data['admin_role'] !== 'super'
            && User::where('is_admin', true)->where('admin_role', 'super')->count() <= 1) {
            return back()->with('status', '至少保留一个超级管理员');
        }

        $before = $user->admin_role;
        $user->update(['admin_role' => $data['admin_role']]);
        audit('admin.role', sprintf('%s 的角色：%s → %s', $user->username,
            AdminAccess::ROLES[$before] ?? '未设置', AdminAccess::ROLES[$data['admin_role']]), $user);

        return back()->with('status', "已把 {$user->username} 设为".AdminAccess::ROLES[$data['admin_role']]);
    }

    /** 撤销管理员权限（降为普通用户），带保护 */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('status', '不能撤销自己的管理员权限');
        }
        if (User::where('is_admin', true)->count() <= 1) {
            return back()->with('status', '至少保留一个管理员');
        }
        if ($user->admin_role === 'super'
            && User::where('is_admin', true)->where('admin_role', 'super')->count() <= 1) {
            return back()->with('status', '至少保留一个超级管理员');
        }

        // `[!]` 角色一并清掉。留着的话,下次再提升为管理员时会【悄悄恢复旧角色】——
        // 而提升的人未必知道这个人上次是什么角色。
        $user->update(['is_admin' => false, 'admin_role' => null]);
        audit('admin.revoke', "撤销 {$user->username} 的管理员权限", $user);

        return back()->with('status', "已撤销 {$user->username} 的管理员权限");
    }
}
