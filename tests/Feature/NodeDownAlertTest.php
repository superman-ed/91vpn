<?php

use App\Models\Node;
use App\Models\NodeHealthSpell;
use App\Services\HealthSampler;
use App\Support\Alerter;
use Illuminate\Support\Carbon;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 节点层此前对故障是【完全静默】的:app/Notifications/ 是空目录,
//   MarkNodesOffline 只 $this->info() 打屏。1 台节点时你会立刻发现(全断),
//   10 台时一台死了没人知道 —— 而我们正要买机器。
//
// `[!!]` 告警挂在 HealthSampler 的【区段转换】上,不挂在 MarkNodesOffline 上。
//   理由:MarkNodesOffline 每分钟跑一次、没有去抖,节点抖一下就会发一条;
//   而区段有 MISSES_TO_CLOSE=2 的去抖(5 分钟采样 → 连续失败约 10 分钟才确认),
//   原因也已经算好存在 spell->reason 里。晚 10 分钟但准确,比每分钟刷屏有用。
//
// `[!]` dest 挂掉(L-22)不需要单独一套 —— HealthSampler 的"可用"定义本来就
//   把 dest bad 算成不可用,所以同一个钩子就覆盖了。
// ─────────────────────────────────────────────────────────────────

/** 抓住发出去的消息，不碰网络。 */
function alertSpy(): Alerter
{
    return new class extends Alerter
    {
        public array $sent = [];

        public bool $pretendConfigured = true;

        public function configured(): bool
        {
            return $this->pretendConfigured;
        }

        public function send(string $text): bool
        {
            $this->sent[] = $text;

            return true;
        }
    };
}

function adNode(array $over = []): Node
{
    static $i = 0;
    $i++;

    return Node::create(array_merge([
        'name' => '告警节点'.$i, 'server' => '203.0.113.'.$i, 'port' => 39500,
        'type' => 'vless', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'TOPSECRET'.$i, 'role' => 'landing',
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ], $over));
}

function adSample(Alerter $spy, ?Carbon $at = null): array
{
    return (new HealthSampler(
        app(\App\Services\LayerHealth::class),
        fn (string $h, int $p) => false,   // 端口探不通
        $spy,
    ))->sample($at ?? now());
}

it('节点确认失效后发一条掉线告警', function () {
    $spy = alertSpy();
    $n = adNode();

    adSample($spy);                                   // 建立区段
    expect($spy->sent)->toBeEmpty();                  // 还没出事,不该发

    $n->update(['last_heartbeat' => time() - 9999]);   // 心跳断了
    adSample($spy, now()->addMinutes(5));             // 第 1 次 miss —— 去抖,不发
    expect($spy->sent)->toBeEmpty();

    adSample($spy, now()->addMinutes(10));            // 第 2 次 miss —— 确认失效
    expect($spy->sent)->toHaveCount(1);
    expect($spy->sent[0])->toContain('节点不可用')->toContain((string) $n->id)->toContain($n->name);
});

// `[!!]` 为自己的操作收告警,是训练自己忽略告警最快的办法。
it('人工停用不发告警 —— 那是运维动作不是故障', function () {
    $spy = alertSpy();
    $n = adNode();
    adSample($spy);

    $n->update(['enabled' => false]);                 // reason = manual → censored
    adSample($spy, now()->addMinutes(5));
    adSample($spy, now()->addMinutes(10));

    expect(NodeHealthSpell::where('node_id', $n->id)->first()->outcome)->toBe('censored');
    expect($spy->sent)->toBeEmpty();
});

it('恢复时发一条恢复告警', function () {
    $spy = alertSpy();
    $n = adNode();
    adSample($spy);
    $n->update(['last_heartbeat' => time() - 9999]);
    adSample($spy, now()->addMinutes(5));
    adSample($spy, now()->addMinutes(10));            // 掉线告警
    expect($spy->sent)->toHaveCount(1);

    $n->update(['last_heartbeat' => time()]);          // 回来了
    adSample($spy, now()->addMinutes(15));

    expect($spy->sent)->toHaveCount(2);
    expect($spy->sent[1])->toContain('节点恢复')->toContain($n->name);
});

// `[!!]` 新买的机器第一次上线不该被报成"恢复" —— 那会让人以为它出过事。
it('新节点第一次上线不报恢复', function () {
    $spy = alertSpy();
    adNode();

    adSample($spy);

    expect($spy->sent)->toBeEmpty();
});

// `[!!]` 这一条补的是做反证时发现的缺口:上面那条只证明了「没有任何旧区段」
//   的新节点不报恢复(靠 $prevFailed 的 null 检查),而
//   where('outcome','failed') 这个过滤保护的是【另一个场景】——
//   手动停用(censored)之后再启用,那是运维动作的收尾,不是"从故障中恢复"。
//   为自己的停用-启用收一条"恢复"告警,同样是在训练自己忽略告警。
it('手动停用后再启用不报恢复 —— 那不是从故障中恢复', function () {
    $spy = alertSpy();
    $n = adNode();
    adSample($spy);

    $n->update(['enabled' => false]);                 // censored
    adSample($spy, now()->addMinutes(5));
    adSample($spy, now()->addMinutes(10));
    expect(NodeHealthSpell::where('node_id', $n->id)->first()->outcome)->toBe('censored');
    expect($spy->sent)->toBeEmpty();

    $n->update(['enabled' => true]);                  // 又启用 → 新区段开启
    adSample($spy, now()->addMinutes(15));

    expect($spy->sent)->toBeEmpty('停用后再启用被报成了"恢复"');
});

// `[!!]` 面板出故障时可能十几个节点同时掉。逐条发会把人淹掉,
//   而被淹掉的告警等于没有告警。
it('同时掉多个节点时合并成一条消息', function () {
    $spy = alertSpy();
    $a = adNode();
    $b = adNode();
    $c = adNode();
    adSample($spy);

    foreach ([$a, $b, $c] as $n) {
        $n->update(['last_heartbeat' => time() - 9999]);
    }
    adSample($spy, now()->addMinutes(5));
    adSample($spy, now()->addMinutes(10));

    expect($spy->sent)->toHaveCount(1);               // 一条,不是三条
    expect($spy->sent[0])->toContain('3 个')
        ->toContain($a->name)->toContain($b->name)->toContain($c->name);
});

// `[!!]` Telegram 的聊天记录不受我们控制,而且会被转发。
it('告警内容不含任何机密 —— 不带 secret、不带 IP', function () {
    $spy = alertSpy();
    $n = adNode();
    adSample($spy);
    $n->update(['last_heartbeat' => time() - 9999]);
    adSample($spy, now()->addMinutes(5));
    adSample($spy, now()->addMinutes(10));

    expect($spy->sent)->toHaveCount(1);
    expect($spy->sent[0])->not->toContain($n->secret);
    expect($spy->sent[0])->not->toContain($n->server);
});

// `[!!]` 告警是附加价值,区段记录是本职。不能让前者拖累后者 ——
//   因为告警发不出去而让采样整轮失败,是把通知问题升级成数据问题。
it('告警发送炸了也不影响采样', function () {
    $boom = new class extends Alerter
    {
        public function configured(): bool
        {
            return true;
        }

        public function send(string $text): bool
        {
            throw new \RuntimeException('Telegram 挂了');
        }
    };
    $n = adNode();
    adSample($boom);
    $n->update(['last_heartbeat' => time() - 9999]);
    adSample($boom, now()->addMinutes(5));

    // 这一轮会触发发送 —— 不该把异常抛出来
    $r = adSample($boom, now()->addMinutes(10));

    expect($r['closed'])->toBe(1);                    // 区段照常结束
    expect(NodeHealthSpell::where('node_id', $n->id)->first()->outcome)->toBe('failed');
});

it('没配 token 时静默不发，也不报错', function () {
    $spy = alertSpy();
    $spy->pretendConfigured = false;
    $n = adNode();
    adSample($spy);
    $n->update(['last_heartbeat' => time() - 9999]);
    adSample($spy, now()->addMinutes(5));
    $r = adSample($spy, now()->addMinutes(10));

    expect($spy->sent)->toBeEmpty();
    expect($r['alerted'])->toBe(0);
    expect($r['closed'])->toBe(1);                    // 采样照常
});
