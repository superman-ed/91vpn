<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 转发规则（舰队视角）。
 *
 * 字段含义见 database/migrations/..._create_forward_rules_table.php
 * 与 sogacore 的 docs/RELAY-SCHEMA.md §2.1。
 */
class ForwardRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'listen_all_nics' => 'boolean',
        'port_is_range' => 'boolean',
        'mptcp' => 'boolean',
        'accept_proxy_protocol' => 'boolean',
        'is_shared' => 'boolean',
        'hc_enabled' => 'boolean',
        'inbound_node_set' => 'array',
        'inbound_cred' => 'array',
        'inbound_opts' => 'array',
    ];

    public function outbounds(): HasMany
    {
        return $this->hasMany(ForwardOutbound::class, 'rule_id')->orderBy('sort');
    }

    /** 本规则的入站是否跑在指定节点上。 */
    public function runsOn(int $nodeId): bool
    {
        // 两端都归一成 int 再严格比较:即使 inbound_node_set 因某写入路径(导入/种子)存成
        // ["5"] 字符串,也不会 5 !== "5" 判成"不在此节点跑"→ 静默空下发(无报错、最难查)。
        return in_array($nodeId, array_map('intval', $this->inbound_node_set ?? []), true);
    }
}
