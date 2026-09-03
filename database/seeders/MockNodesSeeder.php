<?php

namespace Database\Seeders;

use App\Models\Node;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Mock 节点:1 个免费体验节点(node_class=0,非会员可连,带节点自带限速)+ 多个付费节点(node_class=1,非会员看到加锁)。
 * 名字对齐客户端 flags.ts 的国旗关键词,方便看到国旗。幂等:按 name updateOrCreate,可反复跑。
 * 运行:php artisan db:seed --class=Database\\Seeders\\MockNodesSeeder
 */
class MockNodesSeeder extends Seeder
{
    public function run(): void
    {
        $nodes = [
            // 免费体验节点(node_class=0):非会员可连,speed_limit=10 演示"跟节点自带限速"
            ['name' => '免费体验 · 香港', 'class' => 0, 'net' => 'tcp', 'speed' => 10, 'rate' => 1.0],

            // 付费节点(node_class=1):非会员/过期用户在列表里看到,但加锁,点击提示订阅
            ['name' => '香港 IEPL 专线 01', 'class' => 1, 'net' => 'ws', 'speed' => 0, 'rate' => 1.0],
            ['name' => '香港 IEPL 专线 02', 'class' => 1, 'net' => 'ws', 'speed' => 0, 'rate' => 1.0],
            ['name' => '日本 东京 BGP', 'class' => 1, 'net' => 'tcp', 'speed' => 0, 'rate' => 1.0],
            ['name' => '日本 大阪 IPLC', 'class' => 1, 'net' => 'ws', 'speed' => 0, 'rate' => 1.5],
            ['name' => '新加坡 狮城直连', 'class' => 1, 'net' => 'tcp', 'speed' => 0, 'rate' => 1.0],
            ['name' => '美国 洛杉矶 CN2', 'class' => 1, 'net' => 'ws', 'speed' => 0, 'rate' => 0.8],
            ['name' => '台湾 台北 Hinet', 'class' => 1, 'net' => 'tcp', 'speed' => 0, 'rate' => 1.0],
            ['name' => '韩国 首尔 SK', 'class' => 1, 'net' => 'tcp', 'speed' => 0, 'rate' => 1.0],
            // 高级节点(node_class=2):演示"会员也可能有更高等级"的加锁
            ['name' => '英国 伦敦 高级专线', 'class' => 2, 'net' => 'ws', 'speed' => 0, 'rate' => 2.0],

            // 回国专线(客户端按名称含"回国"分到"回国专线"tab)
            ['name' => '回国专线 · 上海 BGP', 'class' => 1, 'net' => 'tcp', 'speed' => 0, 'rate' => 1.0],
            ['name' => '回国专线 · 广州移动', 'class' => 1, 'net' => 'ws', 'speed' => 0, 'rate' => 1.0],
        ];

        foreach ($nodes as $i => $n) {
            Node::updateOrCreate(
                ['name' => $n['name']],
                [
                    'server' => 'demo-'.($i + 1).'.91vpn.example',   // 演示地址(非真实落地)
                    'port' => 20000 + $i,
                    'type' => 'vmess',
                    'net' => $n['net'],
                    'traffic_rate' => $n['rate'],
                    'node_class' => $n['class'],
                    'node_group' => 0,
                    'speed_limit' => $n['speed'],
                    'secret' => Str::random(32),
                    'online' => true,
                    'sort' => $i,
                ]
            );
        }

        $this->command?->info('已写入 '.count($nodes).' 个 mock 节点(1 免费体验 + 付费/高级/回国专线)。');
    }
}
