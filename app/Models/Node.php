<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $fillable = [
        'name', 'server', 'port', 'type', 'net', 'host', 'path', 'tls', 'traffic_rate',
        'node_class', 'node_group', 'speed_limit', 'secret',
        'online', 'last_heartbeat', 'sort', 'custom_config',
    ];

    protected $casts = [
        'traffic_rate' => 'decimal:2',
        'online' => 'boolean',
        'tls' => 'boolean',
        'custom_config' => 'array',
    ];
}
