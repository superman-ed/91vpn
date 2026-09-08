<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $fillable = [
        'name', 'server', 'port', 'type', 'net', 'host', 'path', 'tls', 'traffic_rate',
        'node_class', 'node_group', 'speed_limit', 'secret',
        'online', 'enabled', 'role', 'last_heartbeat', 'sort', 'custom_config',
        'flow', 'reality_dest', 'reality_server_names', 'reality_private_key',
        'reality_public_key', 'reality_short_ids', 'accept_proxy_protocol',
    ];

    protected $casts = [
        'traffic_rate' => 'decimal:2',
        'online' => 'boolean',
        'enabled' => 'boolean',
        'tls' => 'boolean',
        'custom_config' => 'array',
        'reality_server_names' => 'array',
        'reality_short_ids' => 'array',
        'accept_proxy_protocol' => 'boolean',
    ];

    /** 是否 REALITY 入站:以 private_key 是否设置为准(下发/订阅的 security 由此派生)。 */
    public function usesReality(): bool
    {
        return ! empty($this->reality_private_key);
    }

    /** 下发/订阅统一的 security 口径:reality > tls > none。 */
    public function securityLayer(): string
    {
        return $this->usesReality() ? 'reality' : ($this->tls ? 'tls' : 'none');
    }

    /** 中转类角色 —— 这些节点会拿到转发规则，且不持有用户名单。 */
    public const RELAY_ROLES = ['relay', 'springboard', 'front', 'both'];

    /**
     * 本节点是否承担转发。
     *
     * [!] landing 请求 /mod_mu/nodes/{id}/routes 会得到 404，agent 据此判定
     * "本节点无中转功能"并停止轮询 —— 那是正常状态，不是故障。
     */
    public function forwards(): bool
    {
        return in_array($this->role, self::RELAY_ROLES, true);
    }

    /**
     * 本节点是否需要用户名单。
     *
     * [decided] D-1（sogacore docs/RELAY-SCHEMA.md §6）：中转/跳板/入口一律
     * 不认证、不持有用户名单，只透传字节；认证只在落地做。
     */
    public function needsUsers(): bool
    {
        return $this->role === 'landing' || $this->role === 'both';
    }
}
