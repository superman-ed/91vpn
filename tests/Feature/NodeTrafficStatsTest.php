<?php

use App\Models\Node;
use App\Models\NodeDailyTraffic;
use App\Models\User;
use App\Services\TrafficService;

function trafNode(float $rate = 1.0): Node
{
    return Node::create(['name' => 'HK', 'server' => 'cp.example.com', 'port' => 100, 'traffic_rate' => $rate, 'secret' => bin2hex(random_bytes(8))]);
}

it('accumulates raw and billed traffic per node per day', function () {
    $node = trafNode(2.0);   // 2x 倍率
    $u = User::factory()->create();

    app(TrafficService::class)->record($node, [
        ['user_id' => $u->id, 'u' => 1000, 'd' => 3000],
    ]);

    $row = NodeDailyTraffic::where('node_id', $node->id)->whereDate('date', today())->first();
    expect($row->u)->toBe(1000);            // 原始上行(未乘倍率)
    expect($row->d)->toBe(3000);            // 原始下行
    expect($row->billed)->toBe(8000);       // 计费 = (1000+3000) * 2
});

it('sums multiple reports into the same day row', function () {
    $node = trafNode(1.0);
    $u = User::factory()->create();
    $svc = app(TrafficService::class);

    $svc->record($node, [['user_id' => $u->id, 'u' => 500, 'd' => 500]]);
    $svc->record($node, [['user_id' => $u->id, 'u' => 200, 'd' => 300]]);

    $row = NodeDailyTraffic::where('node_id', $node->id)->whereDate('date', today())->first();
    expect($row->u)->toBe(700);
    expect($row->d)->toBe(800);
    expect($row->billed)->toBe(1500);
});

it('shows per-node traffic on the node admin page', function () {
    $node = trafNode();
    NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->toDateString(), 'u' => 500 * 1024 * 1024, 'd' => 500 * 1024 * 1024, 'billed' => 1024 ** 3]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin/nodes')
        ->assertOk()
        ->assertSee('流量(今日/累计)')
        ->assertSee('1.00 GB');   // 今日 500MB+500MB
});

it('shows site-wide today and cumulative traffic on the dashboard', function () {
    $node = trafNode();
    NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->toDateString(), 'u' => 1024 ** 3, 'd' => 0, 'billed' => 1024 ** 3]);
    NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->subDays(5)->toDateString(), 'u' => 1024 ** 3, 'd' => 0, 'billed' => 1024 ** 3]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertViewHas('todayTraffic', 1024 ** 3)
        ->assertViewHas('totalTraffic', 2 * 1024 ** 3);
});
