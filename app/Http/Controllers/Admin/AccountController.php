<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 管理员自助改密。
 *
 * [!] 后台此前没有给自己改密的入口(只有"给别的用户重置"),管理员忘了密码
 * 只能到服务器上重置。这里补上:校验当前密码 → 设新密码,复用客户端侧同一套写法。
 */
class AccountController extends Controller
{
    public function edit()
    {
        return view('admin.account');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            // confirmed:要求同时提交 password_confirmation 且一致,防手滑打错新密码把自己锁外面
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['current_password' => '当前密码错误']);
        }

        auth()->user()->update(['password' => Hash::make($data['password'])]);
        audit('admin.password', '管理员修改了自己的登录密码');

        return back()->with('status', '登录密码已修改');
    }
}
