<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一次一键部署的运行记录。
 *
 * [!!] 不持有任何 SSH 凭据（见迁移注释）。它是"事后能查"的载体：
 * 装到哪、结果、实时日志、对端指纹。凭据只活在那次后台进程里。
 */
class DeployRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function running(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }

    /**
     * 追加一行日志。
     *
     * [!] 用原子 append（DB 层 CONCAT），不是"读出来拼好再写回"——
     * 页面轮询与后台写入并发时，读改写会把彼此的输出吞掉。
     */
    public function appendLog(string $line): void
    {
        $chunk = rtrim($line, "\n") . "\n";
        $this->newQuery()->whereKey($this->getKey())->update([
            'log' => \Illuminate\Support\Facades\DB::raw(
                'CONCAT(COALESCE(`log`, ' . $this->getConnection()->getPdo()->quote('') . '), '
                . $this->getConnection()->getPdo()->quote($chunk) . ')'
            ),
            'updated_at' => now(),
        ]);
    }

    public function markRunning(?string $hostKey = null): void
    {
        $this->forceFill([
            'status' => 'running',
            'started_at' => $this->started_at ?? now(),
            'host_key' => $hostKey ?? $this->host_key,
        ])->save();
    }

    public function markOk(?string $agentVersion = null): void
    {
        $this->forceFill([
            'status' => 'ok',
            'agent_version' => $agentVersion,
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => 'failed',
            'reason' => mb_substr($reason, 0, 255),
            'finished_at' => now(),
        ])->save();
    }
}
