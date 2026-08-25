<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
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
