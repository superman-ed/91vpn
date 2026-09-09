<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 节点的【整机网卡】按天用量。
 *
 * [!] 与 NodeTraffic 分开：那张表记的是代理流量（按用户/规则），
 * 这张记的是整机。两者数值不同、用途也不同 —— 混在一起迟早有人
 * 拿整机流量去计费。
 */
class NodeNetTraffic extends Model
{
    protected $table = 'node_net_traffic';

    protected $guarded = ['id'];
}
