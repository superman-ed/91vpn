<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AliveIp extends Model
{
    protected $fillable = ['user_id', 'node_id', 'ip', 'last_seen'];

    protected $casts = ['last_seen' => 'datetime'];

    /** 判定“在线”的时间窗口（秒）：节点约每 60s 上报一次，留 2 倍容差 */
    public const ONLINE_WINDOW = 120;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
