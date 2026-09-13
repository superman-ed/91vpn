<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 转发规则各上游的当前状态（存活 / 活跃连接数）。
 *
 * [!] 快照语义：`reported_at` 太旧就说明节点没在报，那时这里的
 * alive/live 不能当真 —— 页面必须一并显示它有多旧。
 */
class RuleOutboundStatus extends Model
{
    protected $table = 'rule_outbound_status';

    protected $guarded = ['id'];

    protected $casts = [
        'backup' => 'boolean',
        'alive' => 'boolean',
        'reported_at' => 'datetime',
    ];

    /** 超过这个时间没再上报，状态就不能当真了。 */
    public const STALE_MINUTES = 5;

    public function stale(): bool
    {
        // `[!]` 没有 reported_at 一律按陈旧。实践中 upsert 每次都写，
        // 但"没报过"与"报过且还新鲜"绝不能混为一谈 —— 混了就是把未知渲染成健康。
        return $this->reported_at === null
            || $this->reported_at->lt(now()->subMinutes(self::STALE_MINUTES));
    }
}
