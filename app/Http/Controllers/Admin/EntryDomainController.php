<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntryDomain;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 入口域名池管理（v1：纯登记 + 提醒）。
 *
 * 作用 = 复刻 SoCloud 的 cp.paeadiy.com：给中转挂一个稳定域名,订阅发域名不发裸 IP;
 * IP 被墙,只改这域名的 A 记录、客户端无感。本面板【只登记 + 提醒改 DNS】,不自己调 DNS。
 *
 * `[!]` 面板不碰真实 DNS —— 改 A 记录仍由你在域名服务商那边做。这里维护的是
 *   "哪个域名在用、指向哪台中转的哪个 IP、被墙没、该不该改 DNS" 这套账 + 提醒。
 */
class EntryDomainController extends Controller
{
    public function index()
    {
        $domains = EntryDomain::with('node')->orderBy('node_id')->orderByDesc('status')->get();
        // 只有会转发的节点（中转/前置）能当入口域名的前置目标。
        $relays = Node::whereIn('role', Node::RELAY_ROLES)
            ->orderBy('name')->get(['id', 'name', 'server', 'role']);

        return view('admin.entry_domains.index', compact('domains', 'relays'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // 主机名格式:字母数字与连字符的点分标签,挡注入/怪值。
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', 'unique:entry_domains,domain'],
            'node_id' => ['required', 'integer', Rule::exists('nodes', 'id')],
            'pointed_ip' => ['nullable', 'ip'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $relay = Node::findOrFail($data['node_id']);
        abort_unless($relay->forwards(), 422, '入口域名只能前置会转发的中转节点');

        $ed = EntryDomain::create($data + ['status' => 'standby']);
        audit('entry_domain.create', "新增入口域名「{$ed->domain}」→ 中转「{$relay->name}」", $ed);

        return redirect('/admin/entry-domains')->with('status', "已新增 {$ed->domain}（备用）。设为在用后订阅才会发它。");
    }

    /** 设为在用：本域名成为该中转订阅对外发的入口,同中转其余降为备用。 */
    public function activate(EntryDomain $entryDomain)
    {
        EntryDomain::where('node_id', $entryDomain->node_id)
            ->where('id', '!=', $entryDomain->id)->where('status', 'active')
            ->update(['status' => 'standby']);
        $entryDomain->update(['status' => 'active']);
        audit('entry_domain.activate', "入口域名「{$entryDomain->domain}」设为在用", $entryDomain);

        return back()->with('status', "{$entryDomain->domain} 已设为在用。订阅从此发它,别忘了它的 A 记录要指向中转当前 IP。");
    }

    /** 标记被墙:仅改状态、不动订阅(在用被墙时你去轮换 IP 或切备用域名)。 */
    public function block(EntryDomain $entryDomain)
    {
        $entryDomain->update(['status' => 'blocked']);
        audit('entry_domain.block', "入口域名「{$entryDomain->domain}」标记被墙", $entryDomain);

        return back()->with('status', "{$entryDomain->domain} 已标记被墙。去『轮换IP』换后端 IP,或把备用域名『设为在用』。");
    }

    /**
     * 轮换 IP:登记这域名 A 记录现在应指向的新 IP,并提醒你去 DNS 改。
     * 顺手把它恢复成在用(轮换的意图就是让它重新顶上)。
     */
    public function rotate(Request $request, EntryDomain $entryDomain)
    {
        $data = $request->validate(['pointed_ip' => ['required', 'ip']]);
        $entryDomain->update([
            'pointed_ip' => $data['pointed_ip'],
            'last_rotated_at' => now(),
            'status' => 'active',
        ]);
        audit('entry_domain.rotate', "入口域名「{$entryDomain->domain}」轮换指向 {$data['pointed_ip']}", $entryDomain);

        return back()->with('status', "已登记:去 DNS 服务商把 {$entryDomain->domain} 的 A 记录改成 {$data['pointed_ip']}（面板不会替你改）。");
    }

    public function destroy(EntryDomain $entryDomain)
    {
        $name = $entryDomain->domain;
        $entryDomain->delete();
        audit('entry_domain.delete', "删除入口域名「{$name}」");

        return back()->with('status', "已删除 {$name}");
    }
}
