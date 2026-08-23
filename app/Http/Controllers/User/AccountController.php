<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /** GET /user/account */
    public function index()
    {
        return view('user.account', ['user' => auth()->user()]);
    }

    /** POST /user/account/password —— 改登录密码 */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['current_password' => '当前密码错误']);
        }

        auth()->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', '登录密码已修改');
    }

    /** POST /user/account/profile —— 改昵称 */
    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:32']]);
        auth()->user()->update(['name' => $data['name']]);

        return back()->with('status', '昵称已更新');
    }
}
