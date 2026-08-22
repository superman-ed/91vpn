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
        return view('admin.nodes.index', ['nodes' => Node::orderBy('sort')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.nodes.form', ['node' => new Node(['type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['secret'] = Str::random(32);
        Node::create($data);

        return redirect('/admin/nodes')->with('status', '节点已创建');
    }

    public function edit(Node $node)
    {
        return view('admin.nodes.form', ['node' => $node]);
    }

    public function update(Request $request, Node $node)
    {
        $node->update($this->validated($request));

        return redirect('/admin/nodes')->with('status', '节点已更新');
    }

    public function destroy(Node $node)
    {
        $node->delete();

        return redirect('/admin/nodes')->with('status', '节点已删除');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'server' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'type' => ['required', 'in:vmess'],
            'net' => ['required', 'in:tcp,ws'],
            'traffic_rate' => ['required', 'numeric', 'min:0'],
            'node_class' => ['required', 'integer', 'min:0', 'max:9'],
            'node_group' => ['nullable', 'integer', 'min:0'],
            'speed_limit' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'integer'],
        ]);
    }
}
