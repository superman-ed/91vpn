<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// 网站不开放注册:引导用户去客户端注册(账号体系 App 优先)。注册逻辑见 RegistrationService,
// 仅客户端 API(/api/auth/register)调用。
class RegisterController extends Controller
{
    /** GET /register —— 展示"请去客户端注册"提示 */
    public function create()
    {
        return view('auth.register');
    }

    /** POST /register —— 网站不受理注册,直接挡回提示页 */
    public function store(Request $request)
    {
        return redirect('/register')->with('status', '暂不支持在网站注册,请在客户端中注册。');
    }
}
