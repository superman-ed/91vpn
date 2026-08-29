<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class MessageApiController extends Controller
{
    /** GET /api/messages —— 站内信(按时间倒序、近30条)+ 未读数 */
    public function index(Request $request)
    {
        $user = $request->user();

        $items = $user->notifications()->limit(30)->get()->map(fn (UserNotification $n) => [
            'id' => $n->id,
            'title' => $n->title,
            'content' => $n->content,
            'type' => $n->type,
            'pinned' => (bool) $n->pinned,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toDateTimeString(),
        ])->values();

        return response()->json(['ret' => 1, 'data' => [
            'unread' => $user->unreadNotificationCount(),
            'messages' => $items,
        ]]);
    }

    /** POST /api/messages/{notification}/read —— 标记单条已读 */
    public function read(UserNotification $notification, Request $request)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['ret' => 0, 'msg' => '消息不存在'], 404);
        }
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['ret' => 1]);
    }

    /** POST /api/messages/read-all —— 全部已读 */
    public function readAll(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ret' => 1, 'msg' => '已全部标记为已读']);
    }
}
