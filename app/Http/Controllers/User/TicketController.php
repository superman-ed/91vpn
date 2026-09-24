<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index()
    {
        return view('user.tickets.index', [
            'tickets' => auth()->user()->tickets()->latest('updated_at')->get(),
        ]);
    }

    public function create()
    {
        return view('user.tickets.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            // `[!!]` ticket_replies.content 是 TEXT(65,535 字节)且 MySQL 开着
            //   STRICT_TRANS_TABLES —— 超长写入会【抛错】,用户收到的是 HTTP 500,
            //   不是"内容太长"。2026-09-24 实测:1,000 汉字成功,70,000 汉字 500。
            //   而客服工单里用户最常干的事就是【粘贴客户端日志】。
            //   5000 字符 × 3 字节/汉字 = 15,000 字节,离上限还很远。
            //   5000 这个数是跟 NotificationController 的既有口径对齐,不是新定的。
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($data) {
            $ticket = Ticket::create([
                'user_id' => auth()->id(),
                'subject' => $data['subject'],
                'status' => 'open',
                'last_reply_at' => now(),
            ]);
            $ticket->replies()->create([
                'user_id' => auth()->id(),
                'is_admin' => false,
                'content' => $data['content'],
            ]);
            return $ticket;
        });

        return redirect("/user/ticket/{$ticket->id}")->with('status', '工单已提交');
    }

    public function show(Ticket $ticket)
    {
        abort_unless($ticket->user_id === auth()->id(), 403);

        return view('user.tickets.show', [
            'ticket' => $ticket->load('replies.user'),
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->user_id === auth()->id(), 403);
        $data = $request->validate(['content' => ['required', 'string', 'max:5000']]);   // 见 store()

        $ticket->replies()->create(['user_id' => auth()->id(), 'is_admin' => false, 'content' => $data['content']]);
        $ticket->update(['status' => 'open', 'last_reply_at' => now()]);

        return back()->with('status', '已回复');
    }

    /** 用户自助结单：问题解决后可自己关闭 */
    public function close(Ticket $ticket)
    {
        abort_unless($ticket->user_id === auth()->id(), 403);
        $ticket->update(['status' => 'closed']);

        return back()->with('status', '工单已关闭');
    }
}
