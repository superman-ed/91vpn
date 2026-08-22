<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailCodeService;
use Illuminate\Http\Request;

class EmailCodeController extends Controller
{
    public function __construct(private EmailCodeService $emailCode) {}

    /** POST /auth/send —— 发送邮箱验证码 */
    public function send(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->emailCode->send($data['email']);

        return response()->json([
            'ok' => true,
            'message' => '验证码已发送（开发环境请查看服务器日志）',
        ]);
    }
}
