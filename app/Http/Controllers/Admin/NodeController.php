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
        $node->update($this->validated($request));
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

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'server' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'type' => ['required', 'in:vmess'],
            'net' => ['required', 'in:tcp,ws'],
            'host' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'tls' => ['nullable', 'boolean'],
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

        return $data;
    }
}
