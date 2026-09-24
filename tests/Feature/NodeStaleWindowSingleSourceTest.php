<?php

use App\Models\AliveIp;
use App\Models\Device;
use App\Models\Node;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 节点心跳失联窗口(180 秒)曾经在【六个地方各写一遍】:
//     Node::alive() 的默认参数
//     RelayMonitorController::STALE_SEC
//     LayerHealth::HEARTBEAT_STALE_SEC
//     HealthController::NODE_ONLINE_WINDOW
//     NodeDiagnosis 里一个【裸字面量】(连常量名都没有)
//     nodes:mark-offline 的 CLI 默认值
//   取值当时恰好一致,但没有任何机制保证它们一起改 —— 改一处漏掉别处的后果是:
//   同一个节点在不同页面上一个显示在线、一个显示失联,而谁都不报错。
//   (LAUNCH-CHECKLIST 的「不是缺陷」表里记的是"4 处",实际数下来是 6 处。)
//
// `[!]` 这一组【不要求三个家族的数字相同】。它们测的是三件不同的事、
//   各有自己的上报频率:
//     Node::STALE_SEC        180  节点 agent → 面板(每分钟一次,容 3 次漏报)
//     AliveIp::ONLINE_WINDOW 120  用户在线(节点每分钟上报)
//     Device::ONLINE_WINDOW  300  客户端设备(频率未文档化,见 Device.php 的 [?])
//   为了"看起来一致"而强行统一成一个数会丢掉语义。
// ─────────────────────────────────────────────────────────────────

/** 用到节点失联窗口的那几个文件 —— 它们必须取 Node::STALE_SEC,不许自己写数。 */
function staleWindowFiles(): array
{
    return [
        'app/Http/Controllers/Admin/RelayMonitorController.php',
        'app/Services/LayerHealth.php',
        'app/Http/Controllers/Admin/HealthController.php',
        'app/Services/NodeDiagnosis.php',
        'app/Console/Commands/MarkNodesOffline.php',
    ];
}

it('派生常量全部等于唯一来源', function () {
    foreach ([
        [\App\Http\Controllers\Admin\RelayMonitorController::class, 'STALE_SEC'],
        [\App\Services\LayerHealth::class, 'HEARTBEAT_STALE_SEC'],
        [\App\Http\Controllers\Admin\HealthController::class, 'NODE_ONLINE_WINDOW'],
    ] as [$cls, $const]) {
        expect((new ReflectionClass($cls))->getConstant($const))
            ->toBe(Node::STALE_SEC, "{$cls}::{$const} 与 Node::STALE_SEC 不一致了");
    }

    expect((new ReflectionMethod(Node::class, 'alive'))->getParameters()[0]->getDefaultValue())
        ->toBe(Node::STALE_SEC);
});

// `[!!]` 这一条是结构性护栏:防的是"有人图省事又写回一个裸数字"。
//   只看【代码行】,注释里提到 180 是允许的(那是在解释历史)。
//
// `[D]` 它与上面那条「派生常量全部等于唯一来源」是【互补】的,两种退化各自实测过:
//     写回 180 → 本条红,上一条【绿】(180 恰好等于唯一来源)
//     写回 240 → 本条【绿】(它扫的是 180),上一条红
//   删掉任何一条都会留下缺口。
it('那几个文件里不许再出现裸的失联秒数', function () {
    foreach (staleWindowFiles() as $rel) {
        $path = base_path($rel);
        expect(file_exists($path))->toBeTrue("{$rel} 不存在 —— 文件改名了就把本测试一起更新");

        foreach (file($path) as $i => $line) {
            $t = ltrim($line);
            if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*')) {
                continue;   // 注释行跳过
            }
            expect($t)->not->toMatch('/\b'.Node::STALE_SEC.'\b/',
                sprintf('%s:%d 又写回了裸数字 —— 该用 Node::STALE_SEC', $rel, $i + 1));
        }
    }
});

// `[!!]` 行为层面的护栏:不传 --seconds 时必须按 Node::STALE_SEC 判,
//   而不是某个另写的默认值。
it('nodes:mark-offline 不传参数时按唯一来源判离线', function () {
    $now = now()->timestamp;
    $mk = fn (string $name, int $ago, int $port) => Node::create([
        'name' => $name, 'server' => 's', 'port' => $port, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'K'.$port,
        'online' => true, 'last_heartbeat' => $now - $ago,
    ]);

    $stale = $mk('超窗', Node::STALE_SEC + 20, 41001);
    $fresh = $mk('窗内', Node::STALE_SEC - 20, 41002);

    $this->artisan('nodes:mark-offline')->assertSuccessful();

    expect($stale->fresh()->online)->toBeFalse('超过窗口却仍在线 —— 默认值不是 Node::STALE_SEC');
    expect($fresh->fresh()->online)->toBeTrue('窗口内却被判离线 —— 默认值偏小');
});

// `[!]` 三个家族【刻意不同】。这条钉住"别哪天有人把它们强行拉平"。
it('三个在线口径是三件不同的事，不该被拉平成一个数', function () {
    expect(Node::STALE_SEC)->toBe(180);
    expect(AliveIp::ONLINE_WINDOW)->toBe(120);
    expect(Device::ONLINE_WINDOW)->toBe(300);

    expect([Node::STALE_SEC, AliveIp::ONLINE_WINDOW, Device::ONLINE_WINDOW])
        ->toHaveCount(3)
        ->and(count(array_unique([Node::STALE_SEC, AliveIp::ONLINE_WINDOW, Device::ONLINE_WINDOW])))
        ->toBe(3, '三个窗口被统一成同一个数了 —— 它们测的不是同一件事,见本文件顶部说明');
});
