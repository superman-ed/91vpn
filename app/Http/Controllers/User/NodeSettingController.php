<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class NodeSettingController extends Controller
{
    /** GET /user/node —— 连接凭证管理（订阅链接在「下载和教程」/首页获取） */
    public function index()
    {
        $user = auth()->user();

        return view('user.node', [
            'user' => $user,
            'subUrl' => url('/sub/'.$user->invite_token),
        ]);
    }

    /** POST /user/node/reset-sub —— 重置订阅链接 */
    public function resetSub()
    {
        auth()->user()->update(['invite_token' => Str::random(32)]);

        return back()->with('status', '订阅链接已重置，新链接约 10 分钟内生效，请重新导入客户端');
    }

    /** POST /user/node/reset-passwd —— 重置连接凭证（同时换 UUID） */
    public function resetPasswd()
    {
        auth()->user()->update([
            'passwd' => Str::lower(Str::random(6)),
            'uuid' => (string) Str::uuid(),
        ]);

        return back()->with('status', '连接凭证（UUID）已重置，约 10 分钟内生效，请重新导入订阅');
    }
}
