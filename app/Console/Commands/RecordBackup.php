<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * 记下一次备份的结果（由宿主 cron 上的 tools/backup.sh 调用）。
 *
 * `[!!]` 备份跑在宿主机上（要 docker exec 到 db 容器，PHP 容器做不到），
 * 所以它不在 Laravel 的调度里，也就不会自动获得 WATCHED_TASKS 那套心跳。
 * 没有这条回写，备份就只剩一个日志文件 —— 而"写进没人看的日志"
 * 正是那条备了四天空气的 relaypanel cron 的死法。
 */
class RecordBackup extends Command
{
    protected $signature = 'backup:record {--status=ok : ok|fail} {--detail= : 一行说明}';

    protected $description = '记录一次备份的结果，供后台「上线自检」显示';

    /** 缓存键。ServiceReadiness 读同一个。 */
    public const KEY = 'backup_hb';

    public function handle(): int
    {
        $status = $this->option('status') === 'fail' ? 'fail' : 'ok';

        // `[!]` 失败时【不覆盖】上次成功的时间戳 —— 那个时间是"数据最远能恢复到哪"，
        // 是出事时第一个要问的数。把它冲掉，就答不上了。
        $prev = Cache::get(self::KEY, []);
        Cache::forever(self::KEY, [
            'at' => now()->timestamp,
            'status' => $status,
            'detail' => mb_substr((string) $this->option('detail'), 0, 200),
            'last_ok_at' => $status === 'ok' ? now()->timestamp : ($prev['last_ok_at'] ?? null),
        ]);

        $this->info("已记录：{$status}");

        return self::SUCCESS;
    }
}
