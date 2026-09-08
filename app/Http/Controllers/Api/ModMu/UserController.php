<?php

namespace App\Http\Controllers\Api\ModMu;

use App\Http\Controllers\Controller;
use App\Services\AliveIpService;
use App\Services\NodeUserService;
use App\Services\TrafficService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /** GET /mod_mu/users —— 节点拉取可服务用户名单 */
    public function index(Request $request, NodeUserService $service)
    {
        $node = $request->attributes->get('node');

        // [decided] D-1：中转 / 跳板 / 入口一律不认证、不持有用户名单，
        // 只透传字节；认证只在落地做。
        //
        // [!!] 这个判据此前【定义了但从没被调用】—— Node::needsUsers() 存在，
        // 全项目零引用。也就是说给节点标 role=relay，这里照样把真实用户名单
        // 连同凭据发过去，而中转机往往是租来的、最容易被接管的那一台。
        // 现象上还看不出异常：中转不认证用户，多一份名单它也用不着。
        if (! $node->needsUsers()) {
            return response()->json(['ret' => 1, 'data' => []]);
        }

        $users = $service->servableUsers($node);

        // SSPanel mod_mu 契约:用户列表在 `data`。
        //
        // 曾同时输出 `data` 与 `users` 两个键,因为不确定消费端读哪个。
        // 2026-09-04 已在真机确认:自研 agent 的 sspanel 适配器读 `data`
        // (internal/panel/sspanel/sspanel.go 的 envelope 结构),XrayR 同样读 `data`。
        // 双键会把 payload 整整翻一倍 —— 10,000 用户时单次响应从 1.2 MB 变 2.3 MB,
        // 而节点每 30-60 秒拉一次。故收敛为单键。
        return response()->json([
            'ret' => 1,
            'data' => $users,
        ]);
    }

    /** POST /mod_mu/users/traffic —— 节点上报流量 */
    public function addTraffic(Request $request, TrafficService $service)
    {
        $node = $request->attributes->get('node');
        $logs = $request->input('data', []);

        $count = $service->record($node, is_array($logs) ? $logs : []);

        return response()->json(['ret' => 1, 'count' => $count]);
    }

    /** POST /mod_mu/users/aliveip —— 节点上报在线 IP，返回应踢下线的超限 IP */
    public function aliveIp(Request $request, AliveIpService $service)
    {
        $node = $request->attributes->get('node');
        $logs = $request->input('data', []);
        $logs = is_array($logs) ? $logs : [];

        $count = $service->record($node, $logs);

        $userIds = collect($logs)->pluck('user_id')->filter()->unique()->values()->all();
        $blocked = $service->blockedIps($userIds);

        return response()->json(['ret' => 1, 'count' => $count, 'blocked' => $blocked]);
    }

    /** GET /mod_mu/func/ping —— 节点心跳(自研 agent 用) */
    public function ping(Request $request)
    {
        $node = $request->attributes->get('node');
        $node->update(['online' => true, 'last_heartbeat' => now()->timestamp]);

        return response()->json(['ret' => 1]);
    }

    /**
     * POST /mod_mu/nodes/{node}/info —— 节点状态/负载上报(soga 的心跳走这里,实测确认)。
     * soga 每隔几秒 POST 一次,body 里带 uptime/load 等;我们据此更新在线状态与心跳时间。
     */
    public function nodeHeartbeat(Request $request)
    {
        $node = $request->attributes->get('node');
        $node->update(['online' => true, 'last_heartbeat' => now()->timestamp]);

        return response()->json(['ret' => 1]);
    }

    /** GET /mod_mu/func/detect_rules —— 审计规则(XrayR 开机会拉)。暂返回空=不审计,消除报错日志 */
    public function detectRules()
    {
        return response()->json(['ret' => 1, 'data' => []]);
    }

    /** POST /mod_mu/users/detectlog —— 节点上报审计违规。暂只收下,不落库 */
    public function detectLog()
    {
        return response()->json(['ret' => 1]);
    }

    /**
     * GET /mod_mu/nodes/{node}/info —— XrayR(SSPanel 模式)开机拉取本节点的协议/端口/传输配置。
     * ⚠️ 占位:返回我们 Node 表已有的字段。SSPanel 对节点类型/传输的精确编码(sort + server 串)
     * 细节留待步骤② 真机 XrayR 接入时按其解析报错逐字段校准,不在此凭记忆臆造。
     */
    public function nodeInfo(Request $request)
    {
        $node = $request->attributes->get('node');

        return response()->json([
            'ret' => 1,
            'data' => [
                'node_id' => $node->id,
                'name' => $node->name,
                // SSPanel mod_mu 契约:所有连接参数打包进分号串,格式为
                //   <host>;<port>;<alterId>;<network>;<tls>;<k=v|k=v>
                // 我们自研 agent 与 XrayR 读下面的扁平字段;soga 与真正的
                // SSPanel 生态读这个串。与 users 端点的 data/users 双键同理。
                'server' => $this->sspanelServerString($node),
                // 扁平字段(自研 agent 用),保持原样
                'host' => $node->host,
                'port' => $node->port,
                'type' => $node->type,          // vmess 等
                'net' => $node->net,            // tcp/ws...
                'path' => $node->path,
                'tls' => (bool) $node->tls,
                'security' => $node->securityLayer(),   // none|tls|reality(扁平,XrayR/自研兜底)
                'flow' => $node->flow ?: '',            // xtls-rprx-vision
                'accept_proxy_protocol' => (bool) $node->accept_proxy_protocol, // 落地在中转后面时开(读 PROXY 头拿真 IP)
                // REALITY 结构块(扁平消费方用;priv 只在此下发给落地 agent,不进订阅)
                'reality' => $node->usesReality() ? [
                    'dest' => $node->reality_dest,
                    'server_names' => $node->reality_server_names ?? [],
                    'public_key' => $node->reality_public_key,
                    'private_key' => $node->reality_private_key,
                    'short_ids' => $node->reality_short_ids ?? [],
                ] : null,
                'traffic_rate' => (float) $node->traffic_rate,
                'node_class' => (int) $node->node_class,
                'node_speedlimit' => (float) $node->speed_limit,
                'node_group' => (int) $node->node_group,
                'custom_config' => $node->custom_config,
                // SSPanel mod_mu 契约:节点拉取/上报周期由面板集中下发,
                // 优先于节点本地配置。改这里对所有节点生效。
                'base_config' => [
                    'pull_interval' => 60,
                    'push_interval' => 60,
                ],
            ],
        ]);
    }

    /**
     * 拼 SSPanel mod_mu 的 server 分号串。
     *
     * 格式:<host>;<port>;<alterId>;<network>;<tls>;<k=v|k=v>
     *
     * 注意两条实测出来的规则(来自对 soga 的逆向,见 sogacore 项目
     * compatibility/adversarial-sspanel.md):
     *   - network 段【存在但为空】会被拒绝,所以 net 必须有值
     *   - 给了 network 却缺 tls 段(共 4 段)会被拒绝,所以至少要凑满 5 段
     */
    private function sspanelServerString($node): string
    {
        $params = [];
        if ($node->path !== '' && $node->path !== null) {
            $params[] = 'path=' . $node->path;
        }
        if ($node->host !== '' && $node->host !== null) {
            $params[] = 'host=' . $node->host;
        }
        // vless 的 xtls-rprx-vision(可与 reality 或 tls 搭配),独立于 security
        if ($node->flow !== '' && $node->flow !== null) {
            $params[] = 'flow=' . $node->flow;
        }
        if ($node->accept_proxy_protocol) {
            $params[] = 'accept_proxy=1';   // 落地读中转 PROXY v2 头,还原真实客户端 IP
        }
        // REALITY 参数进 params 段,由 agent 的 NodeFromServerString 解析(契约 key:dest/sni/pbk/priv/sid)。
        // [!!] priv(私钥)只随 nodeInfo 下发给落地 agent,【绝不进客户端订阅】——订阅只出 pbk。
        if ($node->usesReality()) {
            $params[] = 'dest=' . $node->reality_dest;
            $params[] = 'sni=' . implode(',', $node->reality_server_names ?? []);
            $params[] = 'pbk=' . $node->reality_public_key;
            $params[] = 'priv=' . $node->reality_private_key;
            $params[] = 'sid=' . implode(',', $node->reality_short_ids ?? []);
        }

        return implode(';', [
            $node->server,
            (string) $node->port,
            '0',                                   // alterId,现代 vmess 一律 0
            $node->net ?: 'tcp',                   // 不能为空串
            $node->securityLayer(),                // none|tls|reality(agent 第5段读它)
            implode('|', $params),
        ]);
    }
}
