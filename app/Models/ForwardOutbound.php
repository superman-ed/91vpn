<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 转发规则的一个出站。见 sogacore 的 docs/RELAY-SCHEMA.md §2.2。
 */
class ForwardOutbound extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'out_mptcp' => 'boolean',
        'source_in_source_out' => 'boolean',
        'trusted_transit' => 'boolean',
        'proxy_mode' => 'boolean',
        'target_node_set' => 'array',
        'out_cred' => 'array',
    ];
}
