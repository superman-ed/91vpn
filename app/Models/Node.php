<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $fillable = [
        'name', 'server', 'port', 'type', 'net', 'host', 'path', 'tls', 'traffic_rate',
        'node_class', 'node_group', 'speed_limit', 'secret',
        'online', 'enabled', 'role', 'last_heartbeat', 'sort', 'custom_config',
        'flow', 'reality_dest', 'reality_server_names', 'reality_private_key',
        'reality_public_key', 'reality_short_ids', 'accept_proxy_protocol',
        'reported_accept_proxy', 'accept_proxy_reported_at',
        'dest_scan_candidates', 'dest_scan_id', 'dest_scan_result', 'dest_scan_at',
        'reported_dest', 'reported_dest_up', 'reported_dest_failures', 'reported_dest_latency_ms', 'reported_dest_degraded', 'dest_reported_at',
        // 中转相关（ADR-008 从 relaypanel 并入）
        'quota_gb', 'quota_reset_day', 'applied_hash', 'fetched_hash',
        'sync_error', 'sync_degraded', 'sync_rules', 'sync_reported_at',
        'uptime_sec', 'load',
    ];

    /**
     * [!!] 与列默认值保持一致：`nodes.role` 的 DB 默认是 landing，而模型没有
     * 对应默认值时，`Node::create()` 返回的【内存对象】role 是 null ——
     * 于是"建完立刻判断"的代码会得到 needsUsers()=false，节点静默不发用户。
     * 现象上看不出来（节点在线、心跳正常，只是没有用户）。
     */
    protected $attributes = [
        'role' => 'landing',
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
        'reported_accept_proxy' => 'boolean',
        'dest_scan_result' => 'array',
        'dest_scan_at' => 'datetime',
        'reported_dest_up' => 'boolean',
        'reported_dest_degraded' => 'boolean',
        'dest_reported_at' => 'datetime',
        'accept_proxy_reported_at' => 'datetime',
    ];

    /** 是否 REALITY 入站:以 private_key 是否设置为准(下发/订阅的 security 由此派生)。 */
    /**
     * dest 探活的展示状态：ok / down / unknown。
     *
     * [!!] 陈旧一律按 unknown,不按 ok:一台停机的节点,它最后一次上报的 true
     * 会永远留在库里 —— 不判过期就等于把"节点死了"渲染成"dest 是好的"。
     * 阈值取 5 分钟(节点上报周期通常 60 秒)。
     */
    public function destHealth(): string
    {
        if (is_null($this->reported_dest_up) || ! $this->dest_reported_at) {
            return 'unknown';
        }
        if ($this->dest_reported_at->lt(now()->subMinutes(5))) {
            return 'unknown';
        }

        return $this->reported_dest_up ? 'ok' : 'down';
    }

    /**
     * 收 PROXY 头的"面板期望值 / 节点实际在跑的值"。
     *
     * [!!] 陈旧一律按未知（reported=null），不按 ok：一台停机的节点，
     * 它最后一次上报的 true 会永远留在库里 —— 不判过期就等于把"节点死了"
     * 渲染成"配对没问题"。阈值 5 分钟（上报周期通常 60 秒）。
     *
     * [!] ADR-008 合并之后这是【本地一次查询】。拆分时它要跨面板走内部 API
     * （LandingPosture + 两侧 token + 宿主网关地址），那套已随合并删掉。
     *
     * @return array{expected:bool,reported:?bool,reported_at:?\Illuminate\Support\Carbon}
     */
    public function acceptProxyPosture(): array
    {
        $fresh = $this->accept_proxy_reported_at
            && $this->accept_proxy_reported_at->gt(now()->subMinutes(5));

        return [
            'expected' => (bool) $this->accept_proxy_protocol,
            'reported' => $fresh && ! is_null($this->reported_accept_proxy)
                ? (bool) $this->reported_accept_proxy : null,
            'reported_at' => $this->accept_proxy_reported_at,
        ];
    }

    /** 候选清单：按换行/逗号切开、去空、去重、小写。 */
    public function destScanCandidates(): array
    {
        $raw = (string) ($this->dest_scan_candidates ?? '');
        $items = preg_split('/[\s,]+/', mb_strtolower($raw)) ?: [];

        return array_values(array_unique(array_filter($items)));
    }

    /**
     * 候选内容的幂等键。内容不变则 id 不变 —— 节点因此不会反复重扫。
     * [!] 强制重扫时由控制器另加时间戳后缀。
     */
    public static function destScanIdFor(array $candidates): string
    {
        sort($candidates);

        return substr(sha1(implode(',', $candidates)), 0, 8);
    }

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

    /** 全部合法角色。[!] 后台表单的白名单校验用它 —— 打错一个字母会落到
     *  DB 默认的 landing，而那意味着中转拿到用户名单。*/
    public const ROLES = ['landing', 'relay', 'springboard', 'front', 'both'];

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
    /**
     * 本计费周期内的整机用量（字节）。
     *
     * [!] 周期起点按 quota_reset_day 算，不是自然月 —— 机房的周期各不相同，
     * 按自然月算会在月初给出一个偏小的假象。
     * （ADR-008：从 relaypanel 原样搬入）
     */
    public function periodBytes(): int
    {
        $day = max(1, min(28, (int) ($this->quota_reset_day ?: 1)));
        $now = now();
        $start = $now->copy()->day($day)->startOfDay();
        if ($now->lt($start)) {
            $start = $start->subMonth();
        }

        return (int) NodeNetTraffic::where('node_id', $this->id)
            ->where('date', '>=', $start->toDateString())
            ->sum(\Illuminate\Support\Facades\DB::raw('up + down'));
    }

    /** 额度用了百分之多少。没设额度返回 null。 */
    public function quotaPercent(): ?float
    {
        $q = (int) ($this->quota_gb ?? 0);
        if ($q <= 0) {
            return null;
        }

        return $this->periodBytes() / ($q * 1024 * 1024 * 1024) * 100;
    }

    /** 供下拉框显示：#3 香港中转 (1.2.3.4:443)。（ADR-008 从 relaypanel 搬入）*/
    public function label(): string
    {
        return "#{$this->id} {$this->name} ({$this->server}".($this->port ? ":{$this->port}" : '').')';
    }

    /** 心跳是否新鲜（中转页用；落地那边看 online）。 */
    public function alive(int $staleSec = 180): bool
    {
        return $this->enabled && (time() - (int) $this->last_heartbeat) <= $staleSec;
    }

    public function needsUsers(): bool
    {
        return $this->role === 'landing' || $this->role === 'both';
    }

    /**
     * 与本节点共用同一个 REALITY dest 的其它节点。
     *
     * `[!!]` dest 共用有【两重】后果，它们相互独立、后果也不同：
     *
     * **① 关联风险（更要紧）**：我们要求 dest 非 CDN，而一个非 CDN 的站
     * 正常只有一两个 IP。若十台落地都声称自己是 `mirrors.example.edu`，
     * 那本身就是异常模式 —— 识别或封禁其中一个，就顺藤摸到全部。
     * **一次识别全灭。**
     *
     * **② 负载叠加**：REALITY 每条用户新连接都要连一次 dest
     * （`compatibility/dest-latency.md`，严格 1:1）。L 台落地共用一个 dest 时，
     * 它承受的是 `Σ C_i` 而不是单台的 C。所以 dest 的容量评价有两个维度：
     * **单落地** 与 **总量** —— 而扫描器只能从一台落地的视角看。
     *
     * `[!]` 只算落地类角色：中转不跑 REALITY，它的 reality_dest 没有意义。
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,Node>
     */
    public function sharingDest()
    {
        if (! $this->usesReality() || (string) $this->reality_dest === '') {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return static::query()
            ->where('reality_dest', $this->reality_dest)
            ->whereIn('role', ['landing', 'both'])
            ->where('id', '!=', $this->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * 能出现在【用户面前】的节点 —— 订阅、节点列表页、客户端 API 都走这个。
     *
     * [decided] D-1 的展示面：中转/跳板/入口不认证用户，用户也【连不上它们】——
     * 它们的 port 恒为 0，监听来自转发规则。
     *
     * [!!] 此前三处面向用户的查询都只筛 online + enabled，谁都没筛 role。
     * 中转 #93 (online=1, enabled=1, port=0) 因此【当时就摆在 /user/servers
     * 和客户端 API 的节点列表里】；没进订阅纯粹是因为它 class=200 碰巧高于
     * 所有用户的等级 —— 那是配置巧合，不是守卫。把中转的存在告诉用户本身
     * 也是多余的：那是内部拓扑。
     *
     * 收敛成一个 scope，是因为漏的方式已经证明了：这类过滤散在三处，
     * 第四处一定还会漏。
     */
    public function scopeUserVisible(Builder $q): Builder
    {
        return $q->whereIn('role', ['landing', 'both']);
    }
}
