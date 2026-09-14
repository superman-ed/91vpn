<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 入口域名（域名池的一条）。见 create_entry_domains_table 迁移的说明。
 */
class EntryDomain extends Model
{
    protected $fillable = ['domain', 'node_id', 'status', 'pointed_ip', 'note', 'last_rotated_at'];

    protected $casts = ['last_rotated_at' => 'datetime'];

    public function node()
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * DNS 可能过期：这域名指向的 IP 和它前置中转的真实 IP 对不上。
     *
     * `[!]` 只有中转的 server 是【IP】时才判得了；server 本身填的是域名时，
     * 面板无从知道它现在解析到哪，返回 false（不误报）。
     */
    public function dnsStale(): bool
    {
        $realIp = (string) ($this->node->server ?? '');
        if ($realIp === '' || ! filter_var($realIp, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $this->pointed_ip !== null && $this->pointed_ip !== '' && $this->pointed_ip !== $realIp;
    }
}
