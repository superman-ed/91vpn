<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    /**
     * `[!!]` 删设备即吊销它的登录凭证 —— 两张表的不变量在这里保证，
     * 而不是靠每个删除点自己记得。
     *
     * `[D]` 2026-09-23 实测过孤儿路径：`device_tokens` 只有 `user_id` 的外键，
     * `device_id` 是个裸 string，`devices` 被删后那行 token 会留下，
     * 而且【仍然能通过鉴权】（`/api/user` 返回 200）。
     * 触发它不需要任何人操作：设备超 15 天未上报被 `DeviceService` 自动回收即可。
     *
     * `[!]` 手工移除那条路（`DeviceController::destroy`）本来就删了两边，
     * 所以今天唯一的孤儿来源是自动回收。做成钩子而不是只补那一处，
     * 是因为「靠下一个人记得」正是这个洞的成因 —— 两张表由两个并行会话分别建，
     * 没人负责中间那道缝。
     *
     * `[!]` 局限：这是 Eloquent 模型事件，**批量删不触发**
     * （`Device::where(...)->delete()` 走查询构造器，不经过模型）。
     * 当前代码里没有这种删法；将来若要加，必须自己一并处理 token。
     */
    protected static function booted(): void
    {
        static::deleting(function (self $device) {
            DeviceToken::where('user_id', $device->user_id)
                ->where('device_id', $device->device_id)
                ->delete();
        });
    }

    protected $fillable = [
        'user_id', 'device_id', 'platform', 'brand', 'model',
        'os_version', 'app_version', 'ip', 'last_seen',
    ];

    protected $casts = ['last_seen' => 'datetime'];

    /** 在线判定窗口（秒）：与节点在线口径一致，客户端约每分钟心跳一次 */
    public const ONLINE_WINDOW = 300;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
