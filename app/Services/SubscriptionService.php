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
        if (! $user->isActive()) {
            throw new RuntimeException('账号已过期或被封禁');
        }
        if ($user->isTrafficExhausted()) {
            throw new RuntimeException('流量已用尽');
        }
    }

    /** v2rayN/NG 格式：base64( 多行 vmess://base64(JSON) ) */
    public function generateV2rayN(User $user): string
    {
        $this->assertUsable($user);

        $lines = $this->accessibleNodes($user)->map(function (Node $n) use ($user) {
            $conf = [
                'v' => '2', 'ps' => $n->name, 'add' => $n->server, 'port' => (string) $n->port,
                'id' => $user->uuid, 'aid' => '0', 'scy' => 'auto', 'net' => $n->net,
                'type' => 'none', 'host' => '', 'path' => '', 'tls' => '',
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

    /** 按等级筛出用户能连的节点（class >= node_class） */
    private function accessibleNodes(User $user)
    {
        return Node::where('online', true)
            ->where('node_class', '<=', $user->class)
            ->orderBy('sort')
            ->get();
    }

    /** 单个节点转 Clash vmess 条目（注入用户 uuid） */
    private function nodeToProxy(Node $node, User $user): array
    {
        return [
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
    }

    private function loadTemplate(): array
    {
        return Yaml::parseFile(resource_path('clash/rules.yaml'));
    }
}
