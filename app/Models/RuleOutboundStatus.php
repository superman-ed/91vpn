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
        'slow' => 'boolean',
        'reported_at' => 'datetime',
    ];

    public function rule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ForwardRule::class, 'rule_id');
    }

    /**
     * 这一行的 alive 到底【有没有被测过】。
     *
     * `[!!]` 规则没开健康检查时，节点侧的探测器【根本不装配】，
     * 而上报走的是选路层的 dead 表 —— 那张表只有探测器会写。
     * 没有探测器 → 永远没人写 → alive 恒为 true。
     * 所以这种行里的 `alive=Y` 意思是【没人检查过】，不是"活着"。
     * 2026-09-13 在真实数据上确认：规则 #1 hc_enabled=N，
     * 而它的状态行一直报 alive=Y —— 面板把它渲染成了绿灯。
     *
     * `[!]` 判据放在面板侧：面板本来就知道 hc_enabled，
     * 这样不需要节点升级也能立刻停止发假绿灯。
     */
    public function measured(): bool
    {
        return (bool) ($this->rule?->hc_enabled);
    }

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
