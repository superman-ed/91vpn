<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 按转发规则的流量归因。
 *
 * [!] 与 node_net_traffic（整机网卡）和 node_traffic（用户流量）都不同：
 * 这张表回答"哪条规则占了多少"。三者数值不可互换，不要混用。
 */
class RuleTraffic extends Model
{
    protected $table = 'rule_traffic';

    protected $guarded = ['id'];
}
