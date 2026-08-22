<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class NodeSettingController extends Controller
{
    /** GET /user/node */
    public function index()
    {
        $user = auth()->user();
        $subUrl = url('/sub/'.$user->invite_token);

        return view('user.node', ['user' => $user, 'subUrl' => $subUrl]);
    }

    /** POST /user/node/reset-sub —— 重置订阅链接 */
    public function resetSub()
    {
        auth()->user()->update(['invite_token' => Str::random(32)]);

        return back()->with('status', '订阅链接已重置，请重新导入客户端（旧链接已失效）');
    }

    /** POST /user/node/reset-passwd —— 重置连接密码（同时换 UUID） */
    public function resetPasswd()
    {
        auth()->user()->update([
            'passwd' => Str::lower(Str::random(6)),
            'uuid' => (string) Str::uuid(),
        ]);

        return back()->with('status', '连接密码与 UUID 已重置，请重新导入订阅');
    }
}
