<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 一段连续处于可用状态的时间。见迁移文件里对统计口径的说明。
 */
class NodeHealthSpell extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'left_truncated' => 'boolean',
        'first_healthy_at' => 'datetime',
        'last_healthy_at' => 'datetime',
        'ended_at' => 'datetime',
        'first_miss_at' => 'datetime',
    ];

    /** 结束原因。`[!!]` manual / deleted 是【删失】，不是失效。 */
    public const REASONS = [
        'unreachable' => '心跳停了，且面板也连不上它的端口 —— 机器或网络没了',
        'agent_gone' => '心跳停了，但端口还连得上 —— 机器活着，agent 进程没了',
        'hop_failed' => '到落地那一跳不通（中转自己报的）',
        'dest_down' => 'REALITY 的 dest 不可达 —— 端口在听但没人能完成握手',
        'manual' => '人工停用（删失，不是失效）',
        'deleted' => '节点被删除（删失，不是失效）',
        'unknown' => '判定不出具体原因',
    ];

    /** 这个结束原因算不算"环境把它弄死了"。 */
    public static function isFailure(string $reason): bool
    {
        return ! in_array($reason, ['manual', 'deleted'], true);
    }

    /**
     * 暴露时间（秒）—— 这一段贡献给统计的观察时长。
     *
     * `[!!]` 未结束与已删失的区段【同样贡献暴露时间】，只是不贡献失效事件。
     * 漏掉它们就是"只数死掉的、不数活着的"，失效率会被高估到没有意义。
     */
    public function exposureSeconds(): int
    {
        $end = $this->ended_at ?? $this->last_healthy_at;

        return max(0, $end->getTimestamp() - $this->first_healthy_at->getTimestamp());
    }
}
