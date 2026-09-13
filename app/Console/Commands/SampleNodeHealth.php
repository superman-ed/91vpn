<?php

namespace App\Console\Commands;

use App\Services\HealthSampler;
use Illuminate\Console\Command;

/**
 * 采样节点可用状态，维护存活区段。
 *
 * `[!!]` 只观察，不制造故障 —— 为了统计去杀中转，测出来的是
 * "人工注入故障后的表现"，不是"生产环境中的自然存活时间"。
 */
class SampleNodeHealth extends Command
{
    protected $signature = 'health:sample';

    protected $description = '采样各节点是否可用，维护存活区段（供失效率统计）';

    public function handle(HealthSampler $s): int
    {
        $r = $s->sample();
        $this->info(sprintf('新开 %d、确认 %d、结束 %d', $r['opened'], $r['confirmed'], $r['closed']));

        return self::SUCCESS;
    }
}
