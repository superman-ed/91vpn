<?php

namespace App\Services;

use App\Models\Node;
use App\Models\User;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class SubscriptionService
{
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
