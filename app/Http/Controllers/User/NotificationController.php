<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /user/messages —— 我的消息 */
    public function index()
    {
        $user = auth()->user();

        return view('user.messages', [
            'notifications' => $user->notifications()->paginate(15),
            'unread' => $user->unreadNotificationCount(),
        ]);
    }

    /** POST /user/messages/{notification}/read —— 标记单条已读 */
    public function read(UserNotification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    /** POST /user/messages/read-all —— 全部已读 */
    public function readAll()
    {
        auth()->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', '已全部标记为已读');
    }
}
