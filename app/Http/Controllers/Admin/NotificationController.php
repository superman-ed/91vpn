<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        // 按批次聚合近期发送
        $recent = UserNotification::selectRaw('batch_id, min(title) as title, min(type) as type, max(pinned) as pinned, min(content) as content, min(created_at) as sent_at, count(*) as cnt, sum(read_at is not null) as read_cnt')
            ->whereNotNull('batch_id')
            ->groupBy('batch_id')->orderByDesc('sent_at')->limit(30)->get();

        return view('admin.notifications.index', [
            'recent' => $recent,
            'segments' => self::SEGMENTS,
            'segmentCounts' => collect(self::SEGMENTS)->mapWithKeys(fn ($v, $k) => [$k => $this->segment($k)->count()]),
        ]);
    }

    /** POST /admin/notifications —— 发送 */
    public function store(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:single,batch'],
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'in:system,marketing,notice'],
            // `[!!]` 收件人按【用户名或邮箱】找,不能只认邮箱。
            //   本产品的注册不收邮箱(AuthApiController::register 只要
            //   username + password)—— 只认邮箱的话,这个"单发"功能对
            //   客户端注册的用户【完全不可用】:输用户名连校验都过不去。
            //   [D] 2026-09-24 实测:生产 2 个用户里只有为 epay 对接建的那个有邮箱,
            //       owner 自己的账号 summer 没有。
            'recipient' => ['required_if:mode,single', 'nullable', 'string', 'max:190'],
            'segment' => ['required_if:mode,batch', 'nullable', 'in:'.implode(',', array_keys(self::SEGMENTS))],
        ]);
        $type = $data['type'] ?? 'system';
        $pinned = $request->boolean('pinned');
        $batch = (string) Str::uuid();

        if ($data['mode'] === 'single') {
            $r = trim((string) $data['recipient']);
            $user = User::where('username', $r)->orWhere('email', $r)->first();
            if (! $user) {
                return back()->withErrors(['recipient' => "找不到用户「{$r}」（可填用户名或邮箱）"])->withInput();
            }
            UserNotification::create(['user_id' => $user->id, 'batch_id' => $batch, 'title' => $data['title'], 'content' => $data['content'], 'type' => $type, 'pinned' => $pinned]);
            // `[!]` 用 ident()(username ?: email ?: #id),别用 email —— 多数用户没有
            audit('notification.send', "站内信发给 {$user->ident()}：{$data['title']}");

            return back()->with('status', "已发送给 {$user->ident()}");
        }

        $now = now();
        $count = 0;
        $this->segment($data['segment'])->select('id')->chunkById(500, function ($users) use ($data, $type, $pinned, $batch, $now, &$count) {
            $rows = $users->map(fn ($u) => [
                'user_id' => $u->id, 'batch_id' => $batch, 'title' => $data['title'], 'content' => $data['content'],
                'type' => $type, 'pinned' => $pinned, 'created_at' => $now, 'updated_at' => $now,
            ])->all();
            UserNotification::insert($rows);
            $count += count($rows);
        });
        audit('notification.broadcast', "群发站内信「{$data['title']}」给「".self::SEGMENTS[$data['segment']]."」共 {$count} 人");

        return back()->with('status', "已群发给「".self::SEGMENTS[$data['segment']]."」共 {$count} 人");
    }

    /** PUT /admin/notifications/{batch} —— 编辑该批标题/内容(同步改所有收件人) */
    public function update(Request $request, string $batch)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:5000'],
        ]);
        $affected = UserNotification::where('batch_id', $batch)->update(['title' => $data['title'], 'content' => $data['content']]);
        if ($affected === 0) {
            return back()->with('status', '未找到该批站内信');
        }
        audit('notification.update', "编辑站内信「{$data['title']}」（{$affected} 收件人）");

        return back()->with('status', "已更新该批站内信（{$affected} 位收件人同步生效）");
    }

    /** DELETE /admin/notifications/{batch} —— 撤回(从所有用户消息箱删除) */
    public function destroy(string $batch)
    {
        $title = UserNotification::where('batch_id', $batch)->value('title');
        $deleted = UserNotification::where('batch_id', $batch)->delete();
        audit('notification.withdraw', "撤回站内信「{$title}」（{$deleted} 收件人）");

        return back()->with('status', "已撤回该批站内信（从 {$deleted} 位用户消息箱移除）");
    }

    private function segment(string $key)
    {
        $q = User::query()->where('banned', false);

        return match ($key) {
            'member' => $q->where('class', '>', 0)->where('class_expire', '>', now()),
            'free' => $q->where('class', 0),
            'expiring' => $q->where('class', '>', 0)->whereBetween('class_expire', [now(), now()->addDays(7)]),
            default => $q,
        };
    }
}
