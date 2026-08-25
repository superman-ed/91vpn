<?php

use App\Models\DailyStat;
use App\Models\Node;
use App\Models\NodeDailyTraffic;

it('truncates demo stats tables', function () {
    DailyStat::create(['date' => today()->toDateString(), 'dau' => 5, 'peak_online' => 2, 'new_users' => 1]);
    $node = Node::create(['name' => 'N', 'server' => 'x', 'port' => 1, 'secret' => 's']);
    NodeDailyTraffic::create(['node_id' => $node->id, 'date' => today()->toDateString(), 'u' => 100, 'd' => 200, 'billed' => 300]);

    $this->artisan('demo:clear-stats --force')->assertSuccessful();

    expect(DailyStat::count())->toBe(0);
    expect(NodeDailyTraffic::count())->toBe(0);
});

it('is a no-op when there is nothing to clear', function () {
    $this->artisan('demo:clear-stats --force')
        ->expectsOutputToContain('没有可清理')
        ->assertSuccessful();
});
