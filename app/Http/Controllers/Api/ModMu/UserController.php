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
        $users = $service->servableUsers($request->attributes->get('node'));

        // 同时给 data 和 users:SSPanel mod_mu / XrayR 读 `data`,我们自研 agent 读 `users`。
        // 双键=两个消费端都兼容,且不赌 XrayR 到底读哪个(步骤② 真机确认后可收敛)。
        return response()->json([
            'ret' => 1,
            'data' => $users,
            'users' => $users,
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
                'traffic_rate' => (float) $node->traffic_rate,
                'node_class' => (int) $node->node_class,
                'node_speedlimit' => (float) $node->speed_limit,
                'node_group' => (int) $node->node_group,
                'custom_config' => $node->custom_config,
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

        return implode(';', [
            $node->server,
            (string) $node->port,
            '0',                                   // alterId,现代 vmess 一律 0
            $node->net ?: 'tcp',                   // 不能为空串
            $node->tls ? 'tls' : 'none',
            implode('|', $params),
        ]);
    }
}
