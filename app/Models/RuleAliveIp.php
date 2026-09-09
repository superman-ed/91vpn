<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 转发规则的在线来源 IP。见迁移文件的说明。
 *
 * [!] 与"用户在线 IP"不是一回事：中转不认证用户，这里没有 user_id。
 */
class RuleAliveIp extends Model
{
    protected $table = 'rule_alive_ip';

    protected $guarded = ['id'];

    protected $casts = ['last_seen' => 'datetime'];

    /** 超过这个时间没再出现就当它走了。agent 侧的 TTL 是 10 分钟。 */
    public const STALE_MINUTES = 15;
}
