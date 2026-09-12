<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NodeController extends Controller
{
    public function index()
    {
        $todayByNode = \App\Models\NodeDailyTraffic::whereDate('date', today())
            ->selectRaw('node_id, sum(u + d) as raw, sum(billed) as billed')
            ->groupBy('node_id')->get()->keyBy('node_id');
        $totalByNode = \App\Models\NodeDailyTraffic::selectRaw('node_id, sum(u + d) as raw, sum(billed) as billed')
            ->groupBy('node_id')->get()->keyBy('node_id');

        $nodes = Node::orderBy('sort')->orderBy('id')->get();

        return view('admin.nodes.index', [
            // `[!!]` 额度用量【一次算完】，不要在视图里逐行调 quotaPercent()。
            // 那个方法自己查一次库，视图里再显示一次用量又是一次 —— 12 个设了
            // 额度的节点就多 24 次查询（实测 5 → 29）。节点表本来只要 5 次。
            'periodBytes' => $this->periodBytesFor($nodes),
            'nodes' => $nodes,
            'todayByNode' => $todayByNode,
            'totalByNode' => $totalByNode,
            // 每台落地的"允许中转源 IP":哪些中转的规则把它当出站目标 → 那些中转的 server。
            // 供一键部署落地时预填 accept_proxy 防火墙白名单(ADR-008 P5)。
            'landingSrc' => $this->landingSourcesFor($nodes),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Node>  $nodes
     * @return array<int,array<int,string>>  landing node id => [中转 server IP...]
     */
    /**
     * 每台节点在【它自己的计费周期内】的整机网卡用量，一次查完。
     *
     * `[!]` 周期起点按各自的 quota_reset_day 算，所以不能一条 SQL 全搞定；
     * 但可以按"最早的那个起点"一次拉回来，再在内存里按各自起点求和 ——
     * 换来的是常数次查询而不是 O(节点数)。
     *
     * @param  \Illuminate\Support\Collection<int,Node>  $nodes
     * @return array<int,int> node_id => 周期内字节数
     */
    private function periodBytesFor($nodes): array
    {
        $withQuota = $nodes->filter(fn (Node $n) => (int) ($n->quota_gb ?? 0) > 0);
        if ($withQuota->isEmpty()) {
            return [];
        }

        $starts = [];
        foreach ($withQuota as $n) {
            $day = max(1, min(28, (int) ($n->quota_reset_day ?: 1)));
            $start = now()->day($day)->startOfDay();
            if (now()->lt($start)) {
                $start = $start->subMonth();
            }
            $starts[$n->id] = $start->toDateString();
        }

        $rows = \App\Models\NodeNetTraffic::whereIn('node_id', array_keys($starts))
            ->where('date', '>=', min($starts))
            ->get(['node_id', 'date', 'up', 'down']);

        $out = array_fill_keys(array_keys($starts), 0);
        foreach ($rows as $r) {
            if ((string) $r->date >= $starts[$r->node_id]) {
                $out[$r->node_id] += (int) $r->up + (int) $r->down;
            }
        }

        return $out;
    }

    private function landingSourcesFor($nodes): array
    {
        $serverById = $nodes->pluck('server', 'id');
        $rules = \App\Models\ForwardRule::with('outbounds')->get();
        $out = [];
        foreach ($nodes as $n) {
            if ($n->role !== 'landing') {
                continue;
            }
            $ips = [];
            foreach ($rules as $r) {
                $targetsThis = $r->outbounds->contains(fn ($o) => in_array(
                    (int) $n->id, array_map('intval', $o->target_node_set ?? []), true
                ));
                if (! $targetsThis) {
                    continue;
                }
                foreach ($r->inbound_node_set ?? [] as $rid) {
                    if ($ip = $serverById[(int) $rid] ?? null) {
                        $ips[$ip] = true;
                    }
                }
            }
            $out[$n->id] = array_keys($ips);
        }

        return $out;
    }

    public function create()
    {
        return view('admin.nodes.form', ['node' => new Node(['type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['secret'] = Str::random(32);
        $node = Node::create($data);
        audit('node.create', "创建节点「{$node->name}」", $node);

        return redirect('/admin/nodes')->with('status', '节点已创建');
    }

    public function edit(Node $node)
    {
        return view('admin.nodes.form', ['node' => $node]);
    }

    public function update(Request $request, Node $node)
    {
        $node->update($this->validated($request, $node));
        audit('node.update', "更新节点「{$node->name}」", $node);

        return redirect('/admin/nodes')->with('status', '节点已更新');
    }

    public function destroy(Node $node)
    {
        // node_daily_traffic.node_id 是 cascadeOnDelete 且无软删:直接删会连带抹除该节点历史流量账(对账凭据丢失)。
        // 有流量记录的节点不允许删除,引导改用「禁用」(enabled=false)。
        if (\App\Models\NodeDailyTraffic::where('node_id', $node->id)->exists()) {
            return redirect('/admin/nodes')->with('status', '该节点已有流量记录,不能删除(会连带删除历史流量账)。请改为「禁用」。');
        }

        // [!!] 上面那道只看 node_daily_traffic —— 那是【代理流量】,中转节点
        // 根本不产生。也就是说中转可以被直接删掉,而 rule_traffic /
        // node_net_traffic 的 node_id 都是 cascadeOnDelete:它的中转流量账
        // 会跟着一起消失,和落地节点被挡住的正是同一件事。
        if (\App\Models\RuleTraffic::where('node_id', $node->id)->exists()
            || \App\Models\NodeNetTraffic::where('node_id', $node->id)->exists()) {
            return redirect('/admin/nodes')->with('status',
                '该节点已有中转流量记录,不能删除(会连带删除历史账)。请改为「禁用」。');
        }

        // [!!] forward_rules.inbound_node_set / forward_outbounds.target_node_set
        // 是 JSON 列,【没有外键】—— 删掉节点不会有任何报错,规则里留下一个
        // 指向不存在节点的 id。现象是那条规则悄悄少在一台机器上生效,
        // 而面板哪儿都不会说。所以在这里挡住,并指出是哪几条规则。
        $refs = $this->rulesReferencing($node->id);
        if ($refs !== []) {
            return redirect('/admin/nodes')->with('status',
                '该节点仍被转发规则引用（'.implode('、', $refs).'）,不能删除。请先在规则里移除它。');
        }
        audit('node.delete', "删除节点「{$node->name}」", $node);
        $node->delete();

        return redirect('/admin/nodes')->with('status', '节点已删除');
    }

    /** 重新生成节点通信密钥 */
    public function regenerateSecret(Node $node)
    {
        $node->update(['secret' => Str::random(32)]);
        audit('node.regenerate_secret', "重置节点「{$node->name}」通信密钥", $node);

        return back()->with('status', '节点密钥已重新生成，请同步更新节点后端配置');
    }

    /** 哪些转发规则还引用着这个节点（入站节点集 / 出站目标节点集，两者都是 JSON 列）。 */
    private function rulesReferencing(int $nodeId): array
    {
        $names = [];
        foreach (\App\Models\ForwardRule::with('outbounds')->get() as $rule) {
            $hit = in_array($nodeId, $rule->inbound_node_set ?? [], false);
            foreach ($rule->outbounds as $ob) {
                $hit = $hit || in_array($nodeId, $ob->target_node_set ?? [], false);
            }
            if ($hit) {
                $names[] = $rule->name;
            }
        }

        return $names;
    }

    /**
     * 一键诊断。把散在四个页面的线索一次跑完，直接给结论。
     *
     * [!] 限流:它会向外发起 TCP 连接(探端口)。不限的话,后台的一个按钮
     * 就成了从面板发起扫描的入口 —— 对我们自己的节点无所谓,但请求里的
     * 地址来自节点表,而节点表是可编辑的。
     */
    public function diagnose(Node $node, \App\Services\NodeDiagnosis $dx)
    {
        return response()->json([
            'node' => $node->label(),
            'items' => $dx->run($node),
            'at' => now()->toDateTimeString(),
        ]);
    }

    private function validated(Request $request, ?Node $node = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'server' => ['required', 'string', 'max:255'],
            // [!!] min:0 而不是 min:1：中转/跳板/入口节点【没有自己的端口】——
            // 它的监听来自转发规则，节点上的 port 恒为 0。写死 min:1 的话后台
            // 根本填不出一个合法的中转节点（中转 #93 当初只能用 tinker 建）。
            // 落地节点仍然必须有端口，见下面的 after() 校验。
            'port' => ['required', 'integer', 'min:0', 'max:65535'],
            'type' => ['required', 'in:vmess,vless'],
            'net' => ['required', 'in:tcp,ws'],
            'host' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'tls' => ['nullable', 'boolean'],
            // vless 的 xtls-rprx-vision;白名单与 agent 的 node.Validate 对齐(ADR:只认这两值)
            'flow' => ['nullable', 'in:,xtls-rprx-vision'],
            // REALITY:填了 dest 即视为启用;server_names 逗号/换行分隔;密钥保存时自动生成(见下)
            'reality_enabled' => ['nullable', 'boolean'],
            'reality_dest' => ['nullable', 'string', 'max:255'],
            'reality_server_names' => ['nullable', 'string', 'max:1000'],
            'reality_regen' => ['nullable', 'boolean'],
            'accept_proxy_protocol' => ['nullable', 'boolean'],
            // [!!] role 必须是白名单里的值:打错一个字母就会落到 DB 默认(landing),
            // 而一台本该只透传的中转会因此【拿到全部用户名单与凭据】。
            'role' => ['nullable', 'in:'.implode(',', \App\Models\Node::ROLES)],
            'quota_gb' => ['nullable', 'integer', 'min:0'],
            'quota_reset_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'dest_scan_candidates' => ['nullable', 'string', 'max:2000'],
            'dest_scan_rerun' => ['nullable', 'boolean'],
            'traffic_rate' => ['required', 'numeric', 'min:0'],
            'node_class' => ['required', 'integer', 'min:0', 'max:9'],
            'node_group' => ['nullable', 'integer', 'min:0'],
            'speed_limit' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'integer'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        $data['host'] = $data['host'] ?? '';
        $data['path'] = $data['path'] ?? '';
        $data['tls'] = $request->boolean('tls');
        $data['enabled'] = $request->boolean('enabled'); // 对用户开放(排空/维护时取消勾选,agent 照常在线但不再服务用户)
        // C 修:flow 仅 vless 有意义;vmess 强制置空(否则 agent 见 flow 会 ErrFlowNeedsVLESS 拒整节点)
        $data['flow'] = $data['type'] === 'vless' ? ($data['flow'] ?? '') : '';
        $data['accept_proxy_protocol'] = $request->boolean('accept_proxy_protocol');

        // dest 候选筛查:id 取候选内容的哈希 —— 内容不变则 id 不变,节点不会重扫。
        // [!!] 候选变了要把上一轮结果一并清掉:两份结果长得一模一样,
        // 留着旧的会让运维以为新清单已经扫完了。
        $cands = collect(preg_split('/[\s,]+/', mb_strtolower((string) ($data['dest_scan_candidates'] ?? ''))))
            ->filter()->unique()->values()->all();
        $newId = $cands === [] ? null : \App\Models\Node::destScanIdFor($cands);
        if ($newId !== null && $request->boolean('dest_scan_rerun')) {
            $newId .= '-'.now()->timestamp;   // 同一份清单要重扫:换个 id
        }
        // [!] 用 ?-> :新建节点时 $node 是 null,`$node->x ?? null` 在 PHP 8 下
        // 仍会抛 "Attempt to read property on null" 警告。
        if ($newId !== $node?->dest_scan_id) {
            $data['dest_scan_id'] = $newId;
            $data['dest_scan_result'] = null;
            $data['dest_scan_at'] = null;
        }
        unset($data['dest_scan_rerun']);

        // REALITY:type=vless 且勾了启用才配置;否则清空(切回 vmess/普通 vless 不残留旧密钥)
        $realityOn = $data['type'] === 'vless' && $request->boolean('reality_enabled') && ! empty($data['reality_dest']);

        // [!!] vision 组合校验(与前端 check() 同一口径,做服务端硬拦)。
        // 前端只是"选的时候提醒",绕过表单直接 POST 仍能存下坏组合,而
        // vision 选错组合是"装完才连不上"那种难查的失败(agent 拒整节点)。
        // 依据 compatibility/vision-matrix.md:vision 只在 vless + tcp + (tls|reality) 上成立。
        // (flow 上面已保证只有 vless 才非空,故这里不必再判 vless。)
        if ($data['flow'] === 'xtls-rprx-vision') {
            if (($data['net'] ?? 'tcp') !== 'tcp') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'flow' => 'vision 流控只能用在 TCP 传输上（ws/grpc 都不行）',
                ]);
            }
            if (! $data['tls'] && ! $realityOn) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'flow' => 'vision 流控需要 TLS 或 REALITY —— 两个都没开，客户端会连不上',
                ]);
            }
        }

        if ($realityOn) {
            // B 修:dest 必须 host:port —— 手滑漏端口(如 www.apple.com)会让 agent
            // "reality dest unreachable" 全员连不上而面板无提示。漏端口自动补 :443 再强校验。
            $dest = trim((string) $data['reality_dest']);
            if (! str_contains($dest, ':')) {
                $dest .= ':443';
            }
            if (! preg_match('/^[A-Za-z0-9.\-]+:\d{1,5}$/', $dest)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reality_dest' => 'REALITY dest 需为 host:port(如 www.apple.com:443)',
                ]);
            }
            $data['reality_dest'] = $dest;
            $data['reality_server_names'] = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $data['reality_server_names']))));
            // 密钥面板生成(与 xray x25519 对拍一致);仅当缺失或运维勾了"重新生成"才铸造,避免每次保存都换密钥使全员掉线
            $needKey = $request->boolean('reality_regen') || empty($node?->reality_private_key);
            if ($needKey) {
                $kp = \App\Services\Reality::keypair();
                $data['reality_private_key'] = $kp['private_key'];
                $data['reality_public_key'] = $kp['public_key'];
                $data['reality_short_ids'] = [\App\Services\Reality::shortId()];
            } else {
                // 保留旧密钥(避免每次保存换密钥使全员掉线);只更新 dest/server_names
                $data['reality_private_key'] = $node->reality_private_key;
                $data['reality_public_key'] = $node->reality_public_key;
                $data['reality_short_ids'] = $node->reality_short_ids;
            }
        } else {
            $data['reality_dest'] = null;
            $data['reality_server_names'] = null;
            $data['reality_private_key'] = null;
            $data['reality_public_key'] = null;
            $data['reality_short_ids'] = null;
        }
        unset($data['reality_enabled'], $data['reality_regen']);

        // [!] port=0 只对不认证用户的角色成立。落地(含 both)要发订阅，
        // 订阅里 port=0 的节点客户端连不上，而且不会有任何报错 ——
        // 用户只看到"连不上"。所以在这里挡住。
        $role = $data['role'] ?? $node?->role ?? 'landing';
        if ((int) ($data['port'] ?? 0) === 0 && in_array($role, ['landing', 'both'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'port' => '落地节点必须有端口（只有中转/跳板/入口可以是 0，它们的监听来自转发规则）',
            ]);
        }

        return $data;
    }
}
