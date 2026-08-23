<?php

use App\Models\DailyTraffic;
use App\Models\Node;
use App\Models\User;
use App\Services\TrafficService;

it('records daily traffic snapshot on report', function () {
    $node = Node::create(['name' => 'n', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'x']);
    $user = User::factory()->create(['u' => 0, 'd' => 0]);

    app(TrafficService::class)->record($node, [['user_id' => $user->id, 'u' => 1000, 'd' => 2000]]);
    // 再报一次，同一天应累加
    app(TrafficService::class)->record($node, [['user_id' => $user->id, 'u' => 500, 'd' => 500]]);

    $row = DailyTraffic::where('user_id', $user->id)->whereDate('date', now()->toDateString())->first();
    expect($row)->not->toBeNull();
    expect($row->u)->toBe(1500);
    expect($row->d)->toBe(2500);
});
