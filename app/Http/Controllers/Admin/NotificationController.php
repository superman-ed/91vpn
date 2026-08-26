<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /** 群发人群定义 */
    public const SEGMENTS = [
        'all' => '全部用户',
        'member' => '会员(有效)',
        'free' => '免费用户',
        'expiring' => '7天内到期',
    ];

    /** GET /admin/notifications */
    public function index()
    {
        // 近期发送(按标题+时间聚合展示条数)
        $recent = UserNotification::selectRaw('title, type, min(created_at) as sent_at, count(*) as cnt, sum(read_at is not null) as read_cnt')
            ->groupBy('title', 'type', DB::raw('date_format(created_at,"%Y-%m-%d %H:%i")'))
            ->orderByDesc('sent_at')->limit(30)->get();

        return view('admin.notifications.index', [
            'recent' => $recent,
            'segments' => self::SEGMENTS,
            'segmentCounts' => collect(self::SEGMENTS)->mapWithKeys(fn ($v, $k) => [$k => $this->segment($k)->count()]),
        ]);
    }

    /** POST /admin/notifications —— 发送(单发 or 群发) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:single,batch'],
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'in:system,marketing,notice'],
            'email' => ['required_if:mode,single', 'nullable', 'email'],
            'segment' => ['required_if:mode,batch', 'nullable', 'in:'.implode(',', array_keys(self::SEGMENTS))],
        ]);
        $type = $data['type'] ?? 'system';
        $pinned = $request->boolean('pinned');

        if ($data['mode'] === 'single') {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                return back()->withErrors(['email' => '该邮箱用户不存在'])->withInput();
            }
            UserNotification::create(['user_id' => $user->id, 'title' => $data['title'], 'content' => $data['content'], 'type' => $type, 'pinned' => $pinned]);
            audit('notification.send', "站内信发给 {$user->email}：{$data['title']}");

            return back()->with('status', "已发送给 {$user->email}");
        }

        // 群发：分块插入
        $now = now();
        $count = 0;
        $this->segment($data['segment'])->select('id')->chunkById(500, function ($users) use ($data, $type, $pinned, $now, &$count) {
            $rows = $users->map(fn ($u) => [
                'user_id' => $u->id, 'title' => $data['title'], 'content' => $data['content'],
                'type' => $type, 'pinned' => $pinned, 'created_at' => $now, 'updated_at' => $now,
            ])->all();
            UserNotification::insert($rows);
            $count += count($rows);
        });
        audit('notification.broadcast', "群发站内信「{$data['title']}」给「".self::SEGMENTS[$data['segment']]."」共 {$count} 人");

        return back()->with('status', "已群发给「".self::SEGMENTS[$data['segment']]."」共 {$count} 人");
    }

    /** 人群查询 */
    private function segment(string $key)
    {
        $q = User::query()->where('banned', false);

        return match ($key) {
            'member' => $q->where('class', '>', 0)->where('class_expire', '>', now()),
            'free' => $q->where('class', 0),
            'expiring' => $q->where('class', '>', 0)->whereBetween('class_expire', [now(), now()->addDays(7)]),
            default => $q,   // all
        };
    }
}
