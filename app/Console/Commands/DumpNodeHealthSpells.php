<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Models\NodeHealthSpell;
use Illuminate\Console\Command;

/**
 * 把存活区段原样倒出来。
 *
 * `[!!]` 刻意【不算平均存活时间】。当前样本下那个数字是错的：
 * 大量区段到现在都没结束（右删失），它们的真实存活只知道"至少这么久"；
 * 而已结束的那些里还混着人工下线（删失，不是失效）。
 * 拿这两样算平均，会得到一个看起来很像结论的错误数字。
 *
 * 要比的是【失效事件数 / 暴露时间】，而那要等样本够了再算。
 * 这个命令现在只负责把原始数据摆出来，让人能判断"够不够"。
 */
class DumpNodeHealthSpells extends Command
{
    protected $signature = 'health:spells {--role= : 只看某个角色}';

    protected $description = '倒出节点存活区段的原始记录（不做聚合，不下结论）';

    public function handle(): int
    {
        $q = NodeHealthSpell::orderBy('node_id')->orderBy('first_healthy_at');
        if ($role = $this->option('role')) {
            $q->where('role', $role);
        }
        $spells = $q->get();
        if ($spells->isEmpty()) {
            $this->warn('还没有任何区段 —— health:sample 跑过了吗？');

            return self::SUCCESS;
        }
        $names = Node::pluck('name', 'id');

        $this->table(
            ['节点', '角色', '开始', '左截断', '最后确认', '采样', '其中未知', '结束', '判定', '原因', '暴露(天)'],
            $spells->map(fn (NodeHealthSpell $s) => [
                '#'.$s->node_id.' '.mb_substr($names[$s->node_id] ?? '(已删)', 0, 12),
                $s->role,
                $s->first_healthy_at->format('m-d H:i'),
                $s->left_truncated ? '是' : '',
                $s->last_healthy_at->format('m-d H:i'),
                $s->observations,
                $s->unknown_observations,
                $s->ended_at?->format('m-d H:i') ?? '进行中',
                $s->outcome ?? '',
                $s->reason ?? '',
                sprintf('%.2f', $s->exposureSeconds() / 86400),
            ])->all()
        );

        // 只报"有多少"，不报"因此说明什么"。
        $ended = $spells->whereNotNull('ended_at');
        $this->line('');
        $this->line(sprintf(
            '区段 %d 个（进行中 %d、失效 %d、删失 %d）；总暴露 %.2f node-days',
            $spells->count(),
            $spells->whereNull('ended_at')->count(),
            $ended->where('outcome', 'failed')->count(),
            $ended->where('outcome', 'censored')->count(),
            $spells->sum(fn (NodeHealthSpell $s) => $s->exposureSeconds()) / 86400,
        ));
        $this->comment('`[!]` 刻意不算平均存活时间 —— 见本命令的类注释。'
            .'要比的是【失效事件数 / 暴露时间】，等样本够了再算。');

        return self::SUCCESS;
    }
}
