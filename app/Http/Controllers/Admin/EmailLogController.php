<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\EmailCodeService;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    /** GET /admin/system/emails —— 邮件发送记录（可代查当前有效验证码） */
    public function index(Request $request, EmailCodeService $codes)
    {
        // 代查验证码：实时读缓存(不落库)，记审计
        $peekEmail = trim((string) $request->query('peek', ''));
        $peekCode = null;
        $peekDone = false;
        if ($peekEmail !== '') {
            $peekCode = $codes->peek($peekEmail);
            $peekDone = true;
            audit('email.peek_code', "代查 {$peekEmail} 的验证码（".($peekCode ? '命中' : '无有效码').'）');
        }

        $status = $request->query('status');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $base = EmailLog::query()
            ->when($q, fn ($query) => $query->where('to_email', 'like', "%{$q}%"))
            ->when(in_array($status, ['sent', 'failed', 'logged'], true), fn ($query) => $query->where('status', $status))
            ->dateBetween($from, $to);

        // 计数不随状态标签变化(随邮箱/日期变)
        $countBase = EmailLog::query()
            ->when($q, fn ($query) => $query->where('to_email', 'like', "%{$q}%"))
            ->dateBetween($from, $to);

        return view('admin.system.emails', [
            'logs' => $base->latest()->paginate(30)->withQueryString(),
            'status' => $status,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'counts' => [
                'all' => (clone $countBase)->count(),
                'sent' => (clone $countBase)->where('status', 'sent')->count(),
                'failed' => (clone $countBase)->where('status', 'failed')->count(),
                'logged' => (clone $countBase)->where('status', 'logged')->count(),
            ],
            'peekEmail' => $peekEmail,
            'peekCode' => $peekCode,
            'peekDone' => $peekDone,
        ]);
    }
}
