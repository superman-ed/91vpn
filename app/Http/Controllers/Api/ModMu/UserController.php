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

        // [decided] D-1 的【写】方向：中转不认证用户,也就没有"这些字节属于谁"
        // 这个信息 —— 它上报的按用户流量只可能是伪造的。
        //
        // [!!] 这是计费面:一台被接管的中转若能替任意用户记流量,
        // 可以把别人的额度刷爆,或给自己的账号免单。中转的用量走
        // 【按规则】的上报,不走这里。
        if (! $node->needsUsers()) {
            return response()->json(['ret' => 1, 'count' => 0]);
        }

        $logs = $request->input('data', []);

        $count = $service->record($node, is_array($logs) ? $logs : []);

        return response()->json(['ret' => 1, 'count' => $count]);
    }

    /** POST /mod_mu/users/aliveip —— 节点上报在线 IP，返回应踢下线的超限 IP */
    public function aliveIp(Request $request, AliveIpService $service)
    {
        $node = $request->attributes->get('node');

        // [decided] D-1 同上:在线 IP 是审计与设备数限制的依据。
        // 中转没有用户身份,它报上来的 (user, ip) 只能是编的 ——
        // 采纳它等于让被接管的中转能把任意用户挤下线、或污染审计记录。
        if (! $node->needsUsers()) {
            return response()->json(['ret' => 1, 'count' => 0, 'blocked' => []]);
        }

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
        $patch = ['online' => true, 'last_heartbeat' => now()->timestamp];
        // 节点上报它【实际生效】的 accept_proxy(eaca9fe:报的是生效值,false 也报、无 omitempty)。
        // 存下供配对校验;has() 判有没有带这个键——没带(旧 agent)则不动,保留 null 表示"从没报过"。
        if ($request->has('accept_proxy')) {
            $patch['reported_accept_proxy'] = $request->boolean('accept_proxy');
            $patch['accept_proxy_reported_at'] = now();
        }
        // REALITY dest 探活(sogacore bfd8740)。dest 失效是静默的:节点照常监听、
        // 面板一切正常,而没人能完成握手 —— agent 一直在探,这里把它存下来。
        // [!] has() 判有没有带这个键:非 reality 节点不带(agent 侧 omitempty),
        // 旧 agent 也不带,两种都保持 null=从没报过,而不是写成 false。
        if ($request->has('reality_dest_up')) {
            $patch['reported_dest'] = (string) $request->input('reality_dest', '');
            $patch['reported_dest_up'] = $request->boolean('reality_dest_up');
            $patch['reported_dest_failures'] = (int) $request->input('reality_dest_failures', 0);
            $patch['dest_reported_at'] = now();
        }
        $node->update($patch);

        return response()->json(['ret' => 1]);
    }

    /**
     * POST /mod_mu/nodes/{node}/dest_scan —— 节点回报一轮 dest 候选筛查结果。
     *
     * [!!] 只接受与【当前】dest_scan_id 匹配的那一轮。晚到的旧结果必须丢掉:
     * 否则运维刚换了候选清单、页面上却被一份过期结果覆盖,而两者长得一模一样。
     *
     * [!] 结果条数封顶:body 是节点发来的,不设上限等于让它决定我们存多少。
     */
    public function destScan(Request $request)
    {
        $node = $request->attributes->get('node');
        $scanId = (string) $request->input('scan_id');

        if ($scanId === '' || $scanId !== (string) $node->dest_scan_id) {
            return response()->json(['ret' => 1, 'msg' => 'stale scan_id ignored']);
        }
        $results = array_slice((array) $request->input('results', []), 0, 50);
        $node->update([
            'dest_scan_result' => ['scan_id' => $scanId, 'results' => $results],
            'dest_scan_at' => now(),
        ]);

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
                'security' => $node->securityLayer(),   // none|tls|reality(扁平,XrayR 兜底)
                'flow' => $node->flow ?: '',            // 扁平,同上
                'traffic_rate' => (float) $node->traffic_rate,
                'node_class' => (int) $node->node_class,
                'node_speedlimit' => (float) $node->speed_limit,
                'node_group' => (int) $node->node_group,
                // [!!] REALITY/flow/security/accept_proxy 的【权威通道 = custom_config】——
                // agent(sogacore)的 wireCustomConfig 从这里读(契约 key:security/private_key/
                // dest/server_names/short_ids/flow)。priv 只在此下发落地 agent,不进订阅。
                'custom_config' => $this->customConfigFor($node),
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
     * 构造下发给 agent 的 custom_config —— reality/flow/security 的权威通道。
     * key 与 sogacore agent 的 wireCustomConfig 对齐:security / private_key / dest /
     * server_names / short_ids / flow。私钥只在此下发落地 agent(不进订阅)。
     */
    private function customConfigFor($node): array
    {
        $cc = (array) ($node->custom_config ?? []);
        $cc['security'] = $node->securityLayer();          // tls|reality(none 时 agent 忽略)
        if ($node->flow) {
            $cc['flow'] = $node->flow;                     // xtls-rprx-vision
        }
        if ($node->usesReality()) {
            $cc['private_key'] = $node->reality_private_key;
            $cc['dest'] = $node->reality_dest;
            $cc['server_names'] = $node->reality_server_names ?? [];
            $cc['short_ids'] = $node->reality_short_ids ?? [];
        }
        // dest 候选筛查任务(sogacore compatibility/dest-scan.md)。
        // [!!] 只在有候选时下发;id 是幂等键——面板每周期都重发同一份 nodeInfo,
        // 没有它节点会每 60 秒把同一批候选重扫一遍(对第三方是持续的可疑流量)。
        $cands = $node->destScanCandidates();
        if ($cands !== [] && $node->dest_scan_id) {
            $cc['dest_scan'] = ['id' => $node->dest_scan_id, 'candidates' => $cands];
        }

        // accept_proxy:无条件下发(不是仅 true 时才发)——否则节点收不到会回落本地 agent.conf,
        // 那个值面板看不见,配对错开时两端都不报错(见节点侧 eaca9fe 的上报设计)。
        $cc['accept_proxy'] = (bool) $node->accept_proxy_protocol;

        return $cc;
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
        // [!] REALITY/flow/accept_proxy 不走这个分号串:agent(sogacore)是从 custom_config
        // 读它们的(见 wireCustomConfig)。串第 5 段只表达 tls/none(保守,ParseServerString 认;
        // reality 由 custom_config.security 覆盖)。串这里只保留 path/host。
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
            $node->tls ? 'tls' : 'none',           // 仅 tls/none;reality 走 custom_config
            implode('|', $params),
        ]);
    }
}
