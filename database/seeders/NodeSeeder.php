<?php

namespace Database\Seeders;

use App\Models\Node;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NodeSeeder extends Seeder
{
    public function run(): void
    {
        Node::updateOrCreate(
            ['name' => '测试-香港01'],
            [
                'server' => '127.0.0.1',
                'port' => 10086,
                'type' => 'vmess',
                'net' => 'tcp',
                'traffic_rate' => 1,
                'node_class' => 0,
                'node_group' => 0,
                'speed_limit' => 0,
                'secret' => Str::random(32),
                'online' => true,
                'sort' => 1,
            ]
        );
    }
}
