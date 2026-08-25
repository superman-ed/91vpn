<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        ]);
    }

    public function create()
    {
        return view('admin.admins.create');
    }

    /** 添加管理员：可新建账号，或把已有邮箱提升为管理员 */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $existing = User::where('email', $data['email'])->first();

        if ($existing) {
            $existing->update(['is_admin' => true]);
            audit('admin.grant', "将 {$existing->email} 提升为管理员", $existing);

            return redirect('/admin/admins')->with('status', "已将 {$existing->email} 提升为管理员");
        }

        if (empty($data['password'])) {
            throw ValidationException::withMessages(['password' => '新建管理员账号需设置密码（至少 8 位）']);
        }

        User::create([
            'name' => $data['name'] ?: '管理员',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'uuid' => (string) Str::uuid(),
            'passwd' => Str::lower(Str::random(6)),
            'ref_code' => Str::upper(Str::random(8)),
            'invite_token' => Str::random(32),
            'api_token' => Str::random(60),
            'class' => 0,
            'class_expire' => now(),
            'is_admin' => true,
        ]);
        audit('admin.create', "新建管理员账号 {$data['email']}");

        return redirect('/admin/admins')->with('status', "管理员 {$data['email']} 已创建");
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

        $user->update(['is_admin' => false]);
        audit('admin.revoke', "撤销 {$user->email} 的管理员权限", $user);

        return back()->with('status', "已撤销 {$user->email} 的管理员权限");
    }
}
