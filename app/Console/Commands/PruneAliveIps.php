<?php

namespace App\Console\Commands;

use App\Models\AliveIp;
use Illuminate\Console\Command;

class PruneAliveIps extends Command
{
    protected $signature = 'alive-ips:prune';

    protected $description = '删除超出在线窗口的过期 alive_ips 记录';

    public function handle(): int
    {
        $deleted = AliveIp::where('last_seen', '<', now()->subSeconds(AliveIp::ONLINE_WINDOW))->delete();

        $this->info("已清理 {$deleted} 条过期在线 IP 记录");

        return self::SUCCESS;
    }
}
