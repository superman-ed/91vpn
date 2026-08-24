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

        try {
            $this->emailCode->send($data['email']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('邮箱验证码发送失败', ['email' => $data['email'], 'err' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => '邮件发送失败，请稍后重试或联系客服'], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => smtp_configured() ? '验证码已发送至邮箱，请查收（含垃圾箱）' : '验证码已发送（开发环境请查看服务器日志）',
        ]);
    }
}
