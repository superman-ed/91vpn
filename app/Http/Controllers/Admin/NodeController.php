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

        return view('admin.nodes.index', [
            'nodes' => Node::orderBy('sort')->orderBy('id')->get(),
            'todayByNode' => $todayByNode,
            'totalByNode' => $totalByNode,
        ]);
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

    private function validated(Request $request, ?Node $node = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'server' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
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

        return $data;
    }
}
