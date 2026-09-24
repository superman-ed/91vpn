<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketApiController extends Controller
{
    /** GET /api/tickets —— 我的工单列表(最近活动在前) */
    public function index(Request $request)
    {
        $tickets = $request->user()->tickets()
            ->withCount('replies')->latest('updated_at')->get()
            ->map(fn (Ticket $t) => $this->summary($t))->values();

        return response()->json(['ret' => 1, 'data' => $tickets]);
    }

    /** POST /api/tickets —— 新建工单(主题 + 首条内容) */
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

        $userId = $request->user()->id;
        $ticket = DB::transaction(function () use ($data, $userId) {
            $ticket = Ticket::create([
                'user_id' => $userId,
                'subject' => $data['subject'],
                'status' => 'open',
                'last_reply_at' => now(),
            ]);
            $ticket->replies()->create(['user_id' => $userId, 'is_admin' => false, 'content' => $data['content']]);

            return $ticket;
        });

        return response()->json(['ret' => 1, 'data' => $this->detail($ticket->load('replies'))]);
    }

    /** GET /api/tickets/{ticket} —— 工单详情 + 全部往来 */
    public function show(Ticket $ticket, Request $request)
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '工单不存在'], 404);
        }

        return response()->json(['ret' => 1, 'data' => $this->detail($ticket->load('replies'))]);
    }

    /** POST /api/tickets/{ticket}/reply —— 用户追加回复(重开为 open) */
    public function reply(Ticket $ticket, Request $request)
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '工单不存在'], 404);
        }
        $data = $request->validate(['content' => ['required', 'string', 'max:5000']]);   // 见 store() 的说明

        $ticket->replies()->create(['user_id' => $request->user()->id, 'is_admin' => false, 'content' => $data['content']]);
        $ticket->update(['status' => 'open', 'last_reply_at' => now()]);

        return response()->json(['ret' => 1, 'data' => $this->detail($ticket->fresh()->load('replies'))]);
    }

    /** POST /api/tickets/{ticket}/close —— 用户自助结单 */
    public function close(Ticket $ticket, Request $request)
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '工单不存在'], 404);
        }
        $ticket->update(['status' => 'closed']);

        return response()->json(['ret' => 1, 'msg' => '工单已关闭']);
    }

    /** 列表项 */
    private function summary(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'subject' => $t->subject,
            'status' => $t->status,
            'replies_count' => $t->replies_count ?? $t->replies()->count(),
            'last_reply_at' => $t->last_reply_at?->toDateTimeString(),
            'updated_at' => $t->updated_at?->toDateTimeString(),
        ];
    }

    /** 详情(含往来) */
    private function detail(Ticket $t): array
    {
        return array_merge($this->summary($t), [
            'replies' => $t->replies->map(fn (TicketReply $r) => [
                'id' => $r->id,
                'is_admin' => (bool) $r->is_admin,
                'content' => $r->content,
                'created_at' => $r->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }
}
