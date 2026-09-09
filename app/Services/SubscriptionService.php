<?php

namespace App\Services;

use App\Models\Node;
use App\Models\User;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class SubscriptionService
{
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

        $lines = $this->accessibleNodes($user)->map(function (Node $n) use ($user) {
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
                return 'vless://'.$user->uuid.'@'.$n->server.':'.$n->port.'?'.http_build_query($p).'#'.rawurlencode($n->name);
            }
            $conf = [
                'v' => '2', 'ps' => $n->name, 'add' => $n->server, 'port' => (string) $n->port,
                'id' => $user->uuid, 'aid' => '0', 'scy' => 'auto', 'net' => $n->net,
                'type' => 'none', 'host' => $n->host, 'path' => $n->path, 'tls' => $n->tls ? 'tls' : '',
            ];
            return 'vmess://'.base64_encode(json_encode($conf, JSON_UNESCAPED_UNICODE));
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
        $proxies = $nodes->map(fn (Node $n) => $this->nodeToProxy($n, $user))->all();
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

        // 处理 proxy-groups：把 __inject_all_nodes 的组填上全部节点名
        $groups = [];
        foreach ($template['proxy-groups'] as $group) {
            if (($group['__inject_all_nodes'] ?? false) === true) {
                unset($group['__inject_all_nodes']);
                $group['proxies'] = $nodeNames ?: ['DIRECT'];
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

    /** 单个节点转 Clash vmess 条目（注入用户 uuid） */
    private function nodeToProxy(Node $node, User $user): array
    {
        // REALITY 节点:出 vless + reality + vision(mihomo 格式)。
        // [!!] 只带公开子集(public-key/short-id/servername/flow),【私钥绝不进订阅】。
        if ($node->usesReality()) {
            return [
                'name' => $node->name,
                'type' => 'vless',
                'server' => $node->server,
                'port' => $node->port,
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
                'name' => $node->name,
                'type' => 'vless',
                'server' => $node->server,
                'port' => $node->port,
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
            'name' => $node->name,
            'type' => 'vmess',
            'server' => $node->server,
            'port' => $node->port,
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
