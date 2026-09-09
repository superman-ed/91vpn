<?php

namespace App\Http\Controllers\Admin;

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\RuleTraffic;
use App\Services\Audit;
use App\Services\Reality;
use App\Services\RuleCheck;
use App\Services\RuleSync;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 转发规则的增删改查。
 *
 * 字段含义见 sogacore 的 docs/RELAY-SCHEMA.md §2 —— 那份 schema 是三边
 * （本表 / 下发 JSON / agent Go 结构体）冻结过的共识。
 *
 * [!] 本控制器只写 forward_rules / forward_outbounds 两张表；节点在 NodeController。
 */
class RelayRuleController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        // `[!]` 分页必须配搜索。只加分页的话，规则一多，"找到那条 39443 的"
        // 就变成翻页 —— 比不分页还难用。
        $q = ForwardRule::with('outbounds')->orderByDesc('id');
        if ($kw = trim((string) $request->query('q', ''))) {
            $q->where(function ($w) use ($kw) {
                $w->where('name', 'like', "%$kw%")
                    ->orWhere('listen_port', 'like', "%$kw%");
            });
        }
        $rules = $q->paginate(25)->withQueryString();
        $nodes = Node::orderBy('id')->get()->keyBy('id');

        // 近 7 天的归因。`[!]` 这是"哪条规则占了多少"，不是实时用量 ——
        // 下行在连接结束时才结算，长连接会滞后。实时用量看节点的网卡计量。
        // `[!]` 只查当前这一页的规则，不是全表 —— 否则分页省下的查询量
        // 又从这里加回去了。
        $since = now()->subDays(6)->toDateString();
        $traffic = RuleTraffic::where('date', '>=', $since)
            ->whereIn('rule_id', $rules->pluck('id'))
            ->selectRaw('rule_id, SUM(up) AS u, SUM(down) AS d')
            ->groupBy('rule_id')->get()->keyBy('rule_id');

        $kw = trim((string) $request->query('q', ''));
        // 列表上直接标出"会被拒绝"的规则。此前只看抗封那一条，
        // 而拒绝的原因有十几种 —— 少检查的那些照样会让整个节点停摆。
        // `[!]` 先把本页所有落地地址一次问完（PROXY 头配对校验要跨面板查）——
        // 否则每条规则各查一次，冷缓存下就是本页规则条数那么多次 HTTP。
        // 忘了预热也只是慢，不会错。
        $svc = app(\App\Services\ForwardRuleService::class);
        // [!] ADR-008 合并之后这里不再需要"预热"：配对校验查的是本地的
        // nodes 表（同一个库），不再是跨面板的内部 API。
        // 原来的 LandingPosture::warm() 连同那整个类已随合并删除。
        $problems = [];
        foreach ($rules as $r) {
            $problems[$r->id] = RuleCheck::check($r);
        }

        // 每条规则：跑它的那些节点，有几个还没应用面板当下这一份下发。
        //
        // `[!!]` 措辞必须是**节点级**的。节点报的 applied_hash 覆盖它身上
        // 全部规则，不是这一条 —— 说"这条规则未生效"是在编造一个
        // 我们并不掌握的事实。能说的只有"跑这条规则的节点没跟上下发"。
        $sync = RuleSync::forNodes($nodes->filter(fn ($n) => $n->forwards()),
            app(\App\Services\ForwardRuleService::class));
        $lag = [];
        foreach ($rules as $r) {
            $names = [];
            foreach ($r->inbound_node_set ?? [] as $id) {
                $st = $sync[(int) $id] ?? null;
                if ($st && $st['state'] !== 'ok' && $st['state'] !== 'unknown') {
                    $names[] = ($nodes[(int) $id]->name ?? "#$id").'（'.$st['label'].'）';
                }
            }
            $lag[$r->id] = $names;
        }

        return view('admin.rules.index',
            compact('rules', 'nodes', 'traffic', 'kw', 'problems', 'lag'));
    }

    public function create()
    {
        return view('admin.rules.form', [
            'problems' => [],
            'rule' => new ForwardRule([
                'enabled' => true,
                'balance' => 'roundrobin',
                'backup_balance' => 'fallback',
                'hc_enabled' => true,
                'hc_interval_sec' => 10,
                'hc_max_fail' => 3,
                'hc_max_success' => 2,
                'inbound_type' => 'direct',
                'listen_all_nics' => true,
            ]),
            'nodes' => $this->nodeOptions(),
        ]);
    }

    public function edit(ForwardRule $rule)
    {
        $rule->load('outbounds');

        return view('admin.rules.form', [
            'rule' => $rule,
            'nodes' => $this->nodeOptions(),
            // `[!!]` 预演结果在【打开编辑页时就算】，不是等保存后才说。
            // 一条已经保存但会被节点拒绝的规则，此刻正在让那个节点上的
            // 全部中转停摆 —— 不该等人再点一次保存才发现。
            'problems' => RuleCheck::check($rule),
        ]);
    }

    public function store(Request $request)
    {
        $rule = ForwardRule::create($this->validated($request));
        $this->ensureInboundCred($rule);
        $this->syncOutbounds($rule, $request);
        Audit::log('rule.create', 'rule', $rule->id, $rule->name, [], $rule->fresh()->getAttributes());

        return $this->afterSave($rule, '已创建');
    }

    public function update(Request $request, ForwardRule $rule)
    {
        // `[!]` 快照要在改之前取。取晚了 diff 恒为空，日志里每条都是
        // "修改规则" 而没有任何内容 —— 那和不记没区别。
        $before = $rule->getAttributes();
        $outsBefore = $rule->outbounds->map->getAttributes()->all();

        $rule->update($this->validated($request));
        $this->ensureInboundCred($rule);
        $this->syncOutbounds($rule, $request);

        $rule->refresh()->load('outbounds');
        $changes = Audit::diff($before, $rule->getAttributes());
        // 出站是整体替换的，id 会变 —— 逐条 diff 没有意义，
        // 只记"从几个变成几个、拨号目标是什么"。
        $dialOf = fn ($rows) => collect($rows)->map(
            fn ($a) => ($a['target_addr'] ?? '') ?: implode('/', $a['target_node_set'] ?? [])
        )->implode(', ');
        $ob = $dialOf($outsBefore);
        $oa = $dialOf($rule->outbounds->map->getAttributes()->all());
        if ($ob !== $oa) {
            $changes['outbounds'] = ['from' => $ob ?: '(无)', 'to' => $oa ?: '(无)'];
        }
        Audit::logChanges('rule.update', 'rule', $rule->id, $rule->name, $changes);

        return $this->afterSave($rule, '已保存');
    }

    public function destroy(ForwardRule $rule)
    {
        $name = $rule->name;
        Audit::log('rule.delete', 'rule', $rule->id, $name, $rule->getAttributes(), []);
        $rule->delete(); // 出站随外键级联删除

        return redirect('/rules')->with('status', "规则「{$name}」已删除");
    }

    /**
     * 需要凭据的入站类型缺凭据时【自动铸造】。
     *
     * `[decided]` D-2：节点间凭据由面板铸造。既然是面板的职责，就不该
     * 等人想起来去点一下"生成凭据"——
     *
     * [!!] 从前不铸造的后果不是"少个字段"：agent 校验 vless 入站需要
     * credential.uuid，缺了就拒绝【整份】规则。也就是说通过表单新建的
     * 任何 vless/vmess/trojan 中转规则，一保存就让这个节点上【所有】
     * 中转停摆，而界面上看不出任何异常。是跨语言契约测试抓到的。
     *
     * direct 是裸转发，不终结协议，没有凭据可言。
     */
    private function ensureInboundCred(ForwardRule $rule): void
    {
        $needs = ['vmess', 'vless', 'trojan'];
        if (! in_array($rule->inbound_type, $needs, true)) {
            return;
        }
        $cred = $rule->inbound_cred ?? [];
        if (($cred['uuid'] ?? '') !== '') {
            return;
        }
        $cred['uuid'] = (string) Str::uuid();
        $rule->inbound_cred = $cred;
        $rule->save();
    }

    /** 一键生成入站凭据（D-2：节点间凭据由面板铸造）。 */
    public function regenerateCred(ForwardRule $rule)
    {
        $cred = $rule->inbound_cred ?? [];
        $cred['uuid'] = (string) Str::uuid();
        $rule->inbound_cred = $cred;
        $rule->save();
        Audit::log('rule.cred', 'rule', $rule->id, $rule->name);

        // [!] 轮换凭据会让【所有】用旧凭据的下一跳连不上，直到它们也拉到新配置。
        // agent 每 check_interval 拉一次，所以最长一个周期内自愈；
        // 这里明确告诉运维，免得他以为是别的问题。
        return back()->with('status',
            '凭据已重新生成 —— 引用本规则的上游会在下一个拉取周期内自动跟上，期间可能短暂连不上');
    }

    /**
     * 保存之后：若节点会拒绝这条规则，**留在编辑页并说清后果**，
     * 而不是跳回列表说"已保存"。
     *
     * `[!!]` "已保存"和"会生效"是两回事。跳回列表只说保存成功，
     * 运维会以为事情办完了 —— 而那个节点上的【全部】中转其实正在停摆。
     */
    private function afterSave(ForwardRule $rule, string $verb)
    {
        $problems = RuleCheck::check($rule->fresh()->load('outbounds'));
        if (RuleCheck::willReject($problems)) {
            return redirect("/rules/{$rule->id}/edit")
                ->with('status', "规则「{$rule->name}」{$verb}，但【节点会拒绝它】—— 见下方检查结果");
        }

        // `[!]` 说清"已保存"不等于"已生效"。节点要到下一个拉取周期才看到它，
        // 而它还可能拒绝这一份 —— 指一下去哪儿看，比让人以为已经完事好。
        return redirect('/rules')->with('status',
            "规则「{$rule->name}」{$verb} —— 节点会在下一个拉取周期取走；"
            .'是否真的应用了，看列表里的「节点同步」一列。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'enabled' => ['nullable'],
            'speed_limit' => ['nullable', 'integer', 'min:0'],

            'inbound_type' => ['required', 'in:direct,vmess,vless,trojan,socks'],
            'inbound_node_set' => ['required', 'array', 'min:1'],
            'inbound_node_set.*' => ['integer'],
            'listen_port' => ['required', 'string', 'max:32'],
            'listen_nic_ip' => ['nullable', 'string', 'max:64'],
            'inbound_transport' => ['nullable', 'in:tcp,ws,grpc'],
            'inbound_security' => ['nullable', 'in:none,tls,reality'],
            'accept_proxy_protocol' => ['nullable'],

            'balance' => ['required', 'in:roundrobin,iphash,leastconn,leastload,random'],
            'backup_balance' => ['required', 'in:fallback,roundrobin,random'],
            'hc_enabled' => ['nullable'],
            'hc_interval_sec' => ['required', 'integer', 'min:3', 'max:3600'],
            'hc_max_fail' => ['required', 'integer', 'min:1', 'max:100'],
            'hc_max_success' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        // 复选框没勾时根本不会提交，用 boolean() 统一处理。
        foreach (['enabled', 'accept_proxy_protocol', 'hc_enabled'] as $k) {
            $data[$k] = $request->boolean($k);
        }
        // [!!] 表单提交的永远是字符串。而编译服务用 in_array(..., true) 严格比较
        // 节点 id —— 存成 ["60"] 的话 60 !== "60"，规则会被判定为"不在这个节点上跑"，
        // 下发就是空的。保存时就转成 int，别把类型问题留到编译期。
        $data['inbound_node_set'] = array_map('intval', $data['inbound_node_set']);
        $data['listen_all_nics'] = ($data['listen_nic_ip'] ?? '') === '';
        $data['speed_limit'] ??= 0;
        // 端口带 '-' 即为区间。
        $data['port_is_range'] = str_contains($data['listen_port'], '-');
        $data['inbound_opts'] = $this->inboundOpts($request, $data);

        return $data;
    }

    /**
     * 入站的传输 / 安全层附加参数。
     *
     * [!!] 只带【当前形态用得上】的块。agent 对多余的块不宽容：
     * security 不是 reality 却给了 reality 块属于配置矛盾，会被拒。
     * 所以这里按 transport / security 取，而不是把表单里所有字段都塞进去
     * —— 否则运维从 reality 切成 tls 之后，那个 reality 块还留在库里，
     * 下次下发就把整条规则搞挂了。
     */
    private function inboundOpts(Request $request, array $data): ?array
    {
        $opts = [];
        // 已有的密钥对不能被表单覆盖掉：private_key 在表单里是只读展示的。
        $old = $request->input('_keep_inbound_opts');
        $old = is_string($old) ? (json_decode($old, true) ?: []) : [];

        switch ($data['inbound_transport'] ?? '') {
            case 'ws':
                $opts['ws'] = array_filter([
                    'path' => (string) $request->input('in_ws_path', ''),
                    'host' => (string) $request->input('in_ws_host', ''),
                ], fn ($v) => $v !== '');
                break;
            case 'grpc':
                $opts['grpc'] = ['service_name' => (string) $request->input('in_grpc_service', '')];
                break;
        }

        if (($data['inbound_security'] ?? '') === 'reality') {
            $names = preg_split('/[\s,]+/', (string) $request->input('in_reality_names', ''), -1,
                PREG_SPLIT_NO_EMPTY) ?: [];
            $sids = preg_split('/[\s,]+/', (string) $request->input('in_reality_short_ids', ''), -1,
                PREG_SPLIT_NO_EMPTY) ?: [];
            $opts['reality'] = array_filter([
                'dest' => (string) $request->input('in_reality_dest', ''),
                'server_names' => $names,
                // 私钥只从库里来 —— 表单不接受手输，避免贴错一个字符
                // 导致【静默失败】：节点照常起、端口照常通，只有客户端连不上。
                'private_key' => (string) ($old['reality']['private_key'] ?? ''),
                'public_key' => (string) ($old['reality']['public_key'] ?? ''),
                'short_ids' => $sids,
            ], fn ($v) => $v !== '' && $v !== []);
        }

        return $opts === [] ? null : $opts;
    }

    /**
     * 生成 REALITY 密钥对。
     *
     * [!] 私钥留在入站这一侧，公钥要填到【拨向它的那个出站】上 ——
     * 所以两个都存，界面上把公钥摆出来给人复制。
     */
    public function realityKeypair(ForwardRule $rule)
    {
        $opts = $rule->inbound_opts ?? [];
        $kp = Reality::keypair();
        $opts['reality'] = array_merge($opts['reality'] ?? [], [
            'private_key' => $kp['private_key'],
            'public_key' => $kp['public_key'],
        ]);
        if (empty($opts['reality']['short_ids'])) {
            $opts['reality']['short_ids'] = [Reality::shortId()];
        }
        $rule->inbound_opts = $opts;
        $rule->save();
        Audit::log('rule.keypair', 'rule', $rule->id, $rule->name);

        return back()->with('status',
            '已生成 REALITY 密钥对 —— 公钥要填到拨向本规则的那个出站上，否则对端连不上');
    }

    /**
     * 用表单提交的出站【整体替换】旧的。
     *
     * [!] 不做增量比对：出站没有稳定的外部标识（用户看到的只是几行表单），
     * 增量匹配只能靠顺序，改一行顺序就会张冠李戴。整体替换语义清晰。
     * 代价是 id 会变，但 agent 认的是"规则 id + 池内序号"，不认出站 id。
     */
    /**
     * agent 认的出站类型（internal/domain/relay/relay.go 的 OutType）。
     *
     * [!!] 与入站【不对称】：入站有 vless，出站没有。下拉框里去掉了它，
     * 但下拉框挡不住手工构造的表单 —— 而一个非法 out_type 会让 agent
     * 拒绝整份规则，现象是"这个节点上所有中转都停了"，极难查。
     */
    private const OUT_TYPES = ['direct', 'vmess', 'trojan', 'ss', 'socks', 'http'];

    private function syncOutbounds(ForwardRule $rule, Request $request): void
    {
        $rows = $request->input('outbounds', []);

        // [!!] 先把新的都构造出来，全部成功了再替换旧的。
        //
        // 起初是「先删旧的、再逐条建新的」—— 中途任何一条出错（比如缺一个
        // 可选字段），旧的已经删了、新的没建完，规则就变成【零出站】。
        // 而零出站的规则会被 agent 整条跳过，等于这条中转悄悄停了。
        // 实测踩到过：表单没提交 fingerprint，控制器直接取 $row['fingerprint']
        // 报 Undefined array key，出站全没了。
        $built = [];
        $sort = ['primary' => 0, 'backup' => 0];
        foreach ($rows as $row) {
            if (($row['target_addr'] ?? '') === '' && empty($row['target_node_set'])) {
                continue; // 空行，跳过
            }
            $pool = ($row['pool'] ?? 'primary') === 'backup' ? 'backup' : 'primary';
            $type = $row['out_type'] ?? 'direct';
            if (! in_array($type, self::OUT_TYPES, true)) {
                abort(422, "出站类型 {$type} 不受支持（agent 只实现了 "
                    . implode('/', self::OUT_TYPES) . '）');
            }
            // 全部用 ?? 取值：表单里的可选字段没填时根本不会提交，
            // 直接下标访问会抛 Undefined array key。
            $built[] = [
                'rule_id' => $rule->id,
                'pool' => $pool,
                'sort' => $sort[$pool]++,
                'enabled' => true,
                'out_type' => $type,
                'target_node_set' => array_map('intval', $row['target_node_set'] ?? []),
                'target_addr' => ($row['target_addr'] ?? '') ?: null,
                'target_port' => ($row['target_port'] ?? '') ?: null,
                'out_transport' => ($row['out_transport'] ?? '') ?: null,
                'out_security' => ($row['out_security'] ?? '') ?: null,
                'fingerprint' => ($row['fingerprint'] ?? '') ?: null,
                'sni' => ($row['sni'] ?? '') ?: null,
                'send_proxy_protocol' => (int) ($row['send_proxy_protocol'] ?? 0),
                // [!!] 抗封约束 A：不勾这个，agent 会要求这一跳必须伪装
                // （security=tls 或 reality），否则【拒绝整条规则】。
                'trusted_transit' => ! empty($row['trusted_transit']),
                'weight' => (int) ($row['weight'] ?? 0),
                'out_cred' => $this->credOf($row),
                'out_opts' => $this->outOpts($row),
            ];
        }

        // 构造全部成功，这才动数据库。
        $rule->outbounds()->delete();
        foreach ($built as $one) {
            ForwardOutbound::create($one);
        }
    }

    /** 出站的传输 / 安全层附加参数。同入站：只带用得上的块。 */
    private function outOpts(array $row): ?array
    {
        $opts = [];
        switch ($row['out_transport'] ?? '') {
            case 'ws':
                $ws = array_filter([
                    'path' => $row['ws_path'] ?? '',
                    'host' => $row['ws_host'] ?? '',
                ], fn ($v) => $v !== '' && $v !== null);
                if ($ws !== []) {
                    $opts['ws'] = $ws;
                }
                break;
            case 'grpc':
                $opts['grpc'] = ['service_name' => (string) ($row['grpc_service'] ?? '')];
                break;
        }
        if (($row['out_security'] ?? '') === 'reality') {
            // [!!] agent 校验要求出站 reality 必须有 public_key，
            // 且 sni 必须是对端 server_names 之一。少了任何一个，
            // agent 拒绝的是【整份】规则。
            $opts['reality'] = array_filter([
                'public_key' => $row['reality_pubkey'] ?? '',
                'short_id' => $row['reality_short_id'] ?? '',
                'spider_x' => $row['reality_spiderx'] ?? '',
            ], fn ($v) => $v !== '' && $v !== null);
        }
        if (! empty($row['mux_enabled'])) {
            $opts['mux'] = ['enabled' => true,
                'concurrency' => (int) ($row['mux_concurrency'] ?? 8)];
        }

        return $opts === [] ? null : $opts;
    }

    private function credOf(array $row): ?array
    {
        $c = array_filter([
            'uuid' => $row['cred_uuid'] ?? null,
            'password' => $row['cred_password'] ?? null,
            'cipher' => $row['cred_cipher'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $c === [] ? null : $c;
    }

    /** 供下拉框用的节点列表。 */
    private function nodeOptions()
    {
        return Node::where('enabled', true)->orderBy('id')->get();
    }
}
