<?php

namespace App\Services;

use App\Models\Node;
use App\Models\User;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class SubscriptionService
{
    /** 上游状态整表的本次请求缓存。见 hopStatuses()。 */
    private ?\Illuminate\Support\Collection $hopStatuses = null;

    /**
     * 统一入口：按客户端类型(flag)生成对应格式的订阅。
     * flag: clash / v2ray(v2rayN base64) / sub(通用base64) / 其它→默认 clash
     */
    public function generate(User $user, string $flag = 'clash'): string
    {
        return match ($flag) {
            'v2ray', 'v2rayn' => $this->generateV2rayN($user),
            'sub', 'base64' => $this->generateBase64($user),
            default => $this->generateClash($user),
        };
    }

    /** 校验账号有效性（各格式共用） */
    private function assertUsable(User $user): void
    {
        if ($user->banned) {
            throw new RuntimeException('账号已被封禁');
        }
        // 不再硬性要求会员有效期:非会员/过期用户凭剩余流量(签到领的)也能出订阅,
        // accessibleNodes 只会给他们免费节点(node_class=0)。
        // 会员按自身额度判耗尽;非会员额外受免费封顶约束(与节点侧一致)。
        $used = (int) $user->u + (int) $user->d;
        $limit = $user->hasActivePackage()
            ? (int) $user->transfer_enable
            : min((int) $user->transfer_enable, free_traffic_cap_bytes());
        if ($used >= $limit) {
            throw new RuntimeException('流量不足，请签到领取或订阅套餐');
        }
    }

    /** v2rayN/NG 格式：base64( 多行 vmess://base64(JSON) ) */
    public function generateV2rayN(User $user): string
    {
        $this->assertUsable($user);

        // 每个落地按【可达路径】展开：直连一条、每个中转入口各一条。
        $lines = $this->accessibleNodes($user)->flatMap(function (Node $n) use ($user) {
            return collect($this->entrypoints($n))->map(function (array $e) use ($n, $user) {
                $name = $e['label'] === '' ? $n->name : $n->name.' · '.$e['label'];
            // vless(reality / tls / none)出 vless:// 链接;只带公开参数,私钥绝不进订阅
            if ($n->usesReality() || $n->type === 'vless') {
                $p = ['encryption' => 'none', 'type' => $n->net ?: 'tcp'];
                if ($n->flow) {
                    $p['flow'] = $n->flow;
                }
                if ($n->usesReality()) {
                    $p['security'] = 'reality';
                    $p['sni'] = $n->reality_server_names[0] ?? '';
                    $p['fp'] = 'chrome';
                    $p['pbk'] = $n->reality_public_key;
                    $p['sid'] = $n->reality_short_ids[0] ?? '';
                } elseif ($n->tls) {
                    $p['security'] = 'tls';
                    $p['fp'] = 'chrome';
                    if ($n->host !== '') {
                        $p['sni'] = $n->host;
                    }
                }
                if ($n->net === 'ws') {
                    $p['path'] = $n->path ?: '/';
                    if ($n->host !== '') {
                        $p['host'] = $n->host;
                    }
                }
                $p = array_filter($p, fn ($v) => $v !== '' && $v !== null);
                return 'vless://'.$user->uuid.'@'.$e['server'].':'.$e['port'].'?'.http_build_query($p).'#'.rawurlencode($name);
            }
            $conf = [
                'v' => '2', 'ps' => $name, 'add' => $e['server'], 'port' => (string) $e['port'],
                'id' => $user->uuid, 'aid' => '0', 'scy' => 'auto', 'net' => $n->net,
                'type' => 'none', 'host' => $n->host, 'path' => $n->path, 'tls' => $n->tls ? 'tls' : '',
            ];
                return 'vmess://'.base64_encode(json_encode($conf, JSON_UNESCAPED_UNICODE));
            });
        })->implode("\n");

        return base64_encode($lines);
    }

    /** 通用 base64 订阅（同 v2rayN，多客户端通吃） */
    public function generateBase64(User $user): string
    {
        return $this->generateV2rayN($user);
    }

    /**
     * 为用户生成 Clash 配置（YAML）。
     * 逻辑：校验有效 → 按等级筛节点 → 生成 vmess 条目 → 注入规则模板。
     */
    public function generateClash(User $user): string
    {
        if (! $user->isActive()) {
            throw new RuntimeException('账号已过期或被封禁');
        }
        if ($user->isTrafficExhausted()) {
            throw new RuntimeException('流量已用尽');
        }

        $nodes = $this->accessibleNodes($user);
        // 每个落地按【可达路径】展开：直连一条、每个中转入口各一条。
        $proxies = $nodes->flatMap(fn (Node $n) => array_map(
            fn (array $e) => $this->nodeToProxy($n, $user, $e), $this->entrypoints($n)
        ))->all();
        $nodeNames = array_column($proxies, 'name');

        $template = $this->loadTemplate();

        // 注入 proxies
        $config = [
            'mixed-port' => 7897,
            'allow-lan' => true,
            'mode' => 'rule',
            'log-level' => 'info',
            'external-controller' => '127.0.0.1:39597',
            'dns' => [
                'enable' => true,
                'ipv6' => false,
                'nameserver' => ['114.114.114.114', '223.5.5.5'],
            ],
            'proxies' => $proxies,
        ];

        // 处理 proxy-groups。两种占位：
        //   __inject_all_nodes  → 填全部节点名
        //   __inject_auto_first → 填 [自动选择, 故障转移, ...全部节点名]
        //
        // `[!!]` 没有节点时,url-test / fallback 这两个【自动组必须整个去掉】——
        // Clash 里一个 proxies 为空的 url-test 组会让整份配置加载失败,
        // 而用户看到的只是"订阅导入失败",查不到原因。
        // 这种情况真实存在:新用户等级 0 而所有节点都设了门槛。
        $hasNodes = $nodeNames !== [];
        $autoNames = [];
        $groups = [];
        foreach ($template['proxy-groups'] as $group) {
            $isAuto = ($group['__inject_all_nodes'] ?? false) === true
                && in_array($group['type'] ?? '', ['url-test', 'fallback'], true);
            if ($isAuto && ! $hasNodes) {
                continue;   // 无节点：整组丢弃，不是填 DIRECT
            }
            if (($group['__inject_all_nodes'] ?? false) === true) {
                unset($group['__inject_all_nodes']);
                $group['proxies'] = $nodeNames ?: ['DIRECT'];
                if ($isAuto) {
                    $autoNames[] = $group['name'];
                }
            }
            if (($group['__inject_auto_first'] ?? false) === true) {
                unset($group['__inject_auto_first']);
                // `[!]` 自动组排在最前 = 客户端默认选中它。
                // 什么都不做的用户得到的是会自愈的那条；想钉住某个节点的照样能选。
                $group['proxies'] = array_merge($autoNames, $nodeNames ?: ['DIRECT']);
            }
            $groups[] = $group;
        }
        $config['proxy-groups'] = $groups;
        $config['rules'] = $template['rules'];

        return Yaml::dump($config, 6, 2);
    }

    /** 按等级筛出用户能连的节点（会员=其等级内全部;非会员/过期=仅免费节点 node_class=0） */
    private function accessibleNodes(User $user)
    {
        $maxClass = $user->hasActivePackage() ? $user->class : 0;

        return Node::userVisible()      // D-1：中转/跳板/入口不进用户面
            ->where('online', true)
            ->where('enabled', true)   // 排空的节点(enabled=false)不进订阅,阻止新连接
            ->where('node_class', '<=', $maxClass)
            ->orderBy('sort')
            ->get();
    }

    /**
     * 把一个落地节点展开成【用户实际能连的入口】列表。
     *
     * [!!] 订阅里必须发"客户端要连的那一跳"，而不是落地自己的地址。
     * 落地挂在中转后面时，客户端连的是【中转的 IP + 转发规则的监听端口】；
     * 落地那个端口往往还被防火墙锁成只收中转（accept_proxy 姿态）——
     * 把落地地址发出去，用户会得到一个连不上且【没有任何报错】的条目。
     *
     * 之前没暴露，纯粹是因为测试时中转和落地是同一台机器、只差端口号。
     *
     * [!] 从转发规则推导而不是在节点上手填对外地址：手填的那份数据会和规则
     * 各自演化 —— 改了规则的监听端口而忘了回来改节点，订阅就静默失效。
     *
     * 返回 [['server'=>…, 'port'=>…, 'label'=>…], …]，label 是给这条路径的
     * 名字后缀（直连为空）。
     */
    public function entrypoints(Node $landing): array
    {
        $out = [];
        $seen = [];   // 按 server:port 去重：同一可达端点只发一条
        $push = function (string $server, int $port, string $label) use (&$out, &$seen) {
            $key = $server.':'.$port;
            if (isset($seen[$key])) {
                return;                       // 已有(保留先入的 label),不重复发
            }
            $seen[$key] = true;
            $out[] = ['server' => $server, 'port' => $port, 'label' => $label];
        };

        // 直连：accept_proxy 的落地【不能】直连 —— 那个端口上每个连接都必须
        // 带 PROXY 头，直连客户端会被全部拒绝。所以这类节点不发直连条目。
        if ((int) $landing->port > 0 && $landing->accept_proxy_protocol !== true) {
            $push($landing->server, (int) $landing->port, '');
        }

        // 经中转：找出把本落地当作出站目标的规则，取它的入站节点(中转)地址 + 监听端口。
        foreach (\App\Models\ForwardRule::with('outbounds')->where('enabled', true)->get() as $rule) {
            if (! $this->ruleTargets($rule, $landing)) {
                continue;
            }
            $port = $this->firstPort($rule->listen_port);
            if ($port === null) {
                continue;
            }
            foreach ((array) ($rule->inbound_node_set ?? []) as $relayId) {
                $relay = Node::find($relayId);
                // [!] 与直连落地口径一致:enabled 之外还要 online —— 否则失联(心跳停)的
                // 中转仍会被合成进订阅,用户拿到一条连不上且无报错的死条目。
                if (! $relay || ! $relay->enabled || ! $relay->online) {
                    continue;
                }
                // `[!!]` 心跳活着【不代表这条路通】。2026-09-13 实测:一台中转
                // online=Y、心跳 19 秒前、入站端口正常监听,而它到落地那一跳
                // 8 秒超时无回包(防火墙没放行) —— 订阅照样把它发给了用户。
                // 这一跳只有中转自己测得了(面板连不到 accept_proxy 的落地),
                // 所以这里读的是它上报的结果。
                if ($this->hopKnownDead($relay, $rule, $landing)) {
                    continue;
                }
                $push($relay->server, $port, $relay->name);
            }
        }

        return $out;
    }

    /**
     * 这台中转到【这个落地】那一跳，是不是有新鲜证据说明它不通。
     *
     * `[!!]` 判据是「有证据说明死了」,不是「没有证据说明活着」。
     * 没上报过、上报已过期 —— 一律【保留】这条入口:
     *   - 中转刚部署、还没跑完第一轮探测,是最常见的"无数据"场景;
     *   - 而误删一条其实能用的入口,用户是完全无感的(他只是少了个选择),
     *     排查起来却要从订阅一路回溯到上报链路。
     * 客户端那边还有 url-test 兜着(P0-1),所以这里宁可漏判不可误判。
     *
     * `[!]` 要求【全部】匹配的上游都死了才丢。一条规则可以有多个出站指向
     * 同一个落地(地址池),只要还有一个活着这条路就是通的。
     * 这与 Node::relayHopHealth() 的口径【不同】——那个是展示用的,
     * 有任何一跳不通就标黄(因为那确实意味着有一批用户受影响);
     * 这里是"要不要把入口从订阅里拿掉",误判的代价不对称,所以更保守。
     */
    private function hopKnownDead(Node $relay, \App\Models\ForwardRule $rule, Node $landing): bool
    {
        $rows = $this->hopStatuses()
            ->where('rule_id', $rule->id)
            ->where('node_id', $relay->id)
            ->reject->stale();

        if ($rows->isEmpty()) {
            return false;                       // 未知 → 保留
        }

        // `[!]` 按 dial 对上具体是哪个落地。tag 有两种格式(fwd-out-* / relay-*),
        // 不能当连接键;dial 是解析后的 host:port,与落地的 server:port 直接可比。
        $matched = $rows->where('dial', $landing->server.':'.$landing->port);
        $rows = $matched->isNotEmpty() ? $matched : $rows;

        return $rows->every(fn (\App\Models\RuleOutboundStatus $r) => ! $r->alive);
    }

    /** 上游状态整表读一次就够 —— entrypoints() 对每个落地都要查,别按次打库。 */
    private function hopStatuses(): \Illuminate\Support\Collection
    {
        return $this->hopStatuses ??= \App\Models\RuleOutboundStatus::all();
    }

    /** 这条规则的出站里有没有指向该落地的（按节点集或按地址+端口两种写法）。 */
    private function ruleTargets(\App\Models\ForwardRule $rule, Node $landing): bool
    {
        foreach ($rule->outbounds as $ob) {
            if (! $ob->enabled) {
                continue;
            }
            if (in_array($landing->id, (array) ($ob->target_node_set ?? []), false)) {
                return true;
            }
            // 出站也可以直接写地址。端口用宽松比较：表里是字符串，节点上是 int。
            if ($ob->target_addr === $landing->server
                && (string) $ob->target_port === (string) $landing->port) {
                return true;
            }
        }

        return false;
    }

    /**
     * 规则的监听端口可能是单个、范围（`30001-30010`）或逗号列表，取第一个。
     *
     * [!] 订阅里只能给一个端口 —— 范围是给中转自己用的（一条规则占一段口），
     * 客户端连哪个都一样。
     */
    private function firstPort(?string $spec): ?int
    {
        $first = trim(explode(',', (string) $spec)[0]);
        $first = trim(explode('-', $first)[0]);

        return ctype_digit($first) && (int) $first > 0 ? (int) $first : null;
    }

    /** 单个节点转 Clash vmess 条目（注入用户 uuid） */
    /**
     * @param  array{server:string,port:int,label:string}|null  $entry
     *   客户端实际要连的那一跳。为 null 时退回节点自身地址（仅供旧调用方/测试）。
     */
    private function nodeToProxy(Node $node, User $user, ?array $entry = null): array
    {
        $entry ??= ['server' => $node->server, 'port' => (int) $node->port, 'label' => ''];
        $name = $entry['label'] === '' ? $node->name : $node->name.' · '.$entry['label'];
        // REALITY 节点:出 vless + reality + vision(mihomo 格式)。
        // [!!] 只带公开子集(public-key/short-id/servername/flow),【私钥绝不进订阅】。
        if ($node->usesReality()) {
            return [
                'name' => $name,
                'type' => 'vless',
                'server' => $entry['server'],
                'port' => $entry['port'],
                'uuid' => $user->uuid,
                'network' => $node->net ?: 'tcp',
                'udp' => true,
                'tls' => true,
                'flow' => $node->flow ?: '',                 // xtls-rprx-vision
                'servername' => ($node->reality_server_names[0] ?? ''), // SNI 取白名单首个
                'client-fingerprint' => 'chrome',            // uTLS 指纹伪装(REALITY 依赖)
                'reality-opts' => [
                    'public-key' => $node->reality_public_key,
                    'short-id' => ($node->reality_short_ids[0] ?? ''),
                ],
            ];
        }

        // vless + tls(可带 vision flow);非 reality 的现代 vless
        if ($node->type === 'vless') {
            $proxy = [
                'name' => $name,
                'type' => 'vless',
                'server' => $entry['server'],
                'port' => $entry['port'],
                'uuid' => $user->uuid,
                'network' => $node->net ?: 'tcp',
                'udp' => true,
            ];
            if ($node->flow) {
                $proxy['flow'] = $node->flow;
            }
            if ($node->tls) {
                $proxy['tls'] = true;
                $proxy['client-fingerprint'] = 'chrome';
                if ($node->host !== '') {
                    $proxy['servername'] = $node->host;
                }
            }
            if ($node->net === 'ws') {
                $proxy['ws-opts'] = [
                    'path' => $node->path ?: '/',
                    'headers' => $node->host !== '' ? ['Host' => $node->host] : [],
                ];
            }

            return $proxy;
        }

        // 默认 vmess(现有节点,行为不变)
        $proxy = [
            'name' => $name,
            'type' => 'vmess',
            'server' => $entry['server'],
            'port' => $entry['port'],
            'uuid' => $user->uuid,
            'alterId' => 0,
            'cipher' => 'auto',
            'network' => $node->net,
            'udp' => true,
        ];

        if ($node->tls) {
            $proxy['tls'] = true;
            if ($node->host !== '') {
                $proxy['servername'] = $node->host;
            }
        }
        if ($node->net === 'ws') {
            $proxy['ws-opts'] = [
                'path' => $node->path ?: '/',
                'headers' => $node->host !== '' ? ['Host' => $node->host] : [],
            ];
        }

        return $proxy;
    }

    private function loadTemplate(): array
    {
        return Yaml::parseFile(resource_path('clash/rules.yaml'));
    }
}
