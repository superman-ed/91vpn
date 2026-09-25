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
 * 作用：给【订阅里会发出去的那台节点】挂一个稳定域名,订阅发域名不发裸 IP;
 * IP 被墙,只改这域名的 A 记录、客户端无感。本面板【只登记 + 提醒改 DNS】,不自己调 DNS。
 *
 * `[!]` 灵感来自 SoCloud 的 cp.paeadiy.com —— 但那只是【他们的拓扑】恰好把门牌
 *   挂在中转上。本面板不限节点角色:直连落地同样在订阅里发自己的地址,同样需要门牌。
 *
 * `[!]` 面板不碰真实 DNS —— 改 A 记录仍由你在域名服务商那边做。这里维护的是
 *   "哪个域名在用、指向哪台中转的哪个 IP、被墙没、该不该改 DNS" 这套账 + 提醒。
 */
class EntryDomainController extends Controller
{
    public function index()
    {
        $domains = EntryDomain::with('node')->orderBy('node_id')->orderByDesc('status')->get();
        // `[!!]` 不按角色过滤。门牌要解决的是「订阅里发的是会变的 IP」,这件事
        // 与本节点转不转发【无关】—— 直连落地也在订阅里发自己的地址,IP 被墙时
        // 一样只想改一条 A 记录。曾经这里只列 RELAY_ROLES,于是直连落地根本
        // 选不中(见 store() 上方那段)。
        $relays = Node::orderBy('name')->get(['id', 'name', 'server', 'role']);

        return view('admin.entry_domains.index', compact('domains', 'relays'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // 主机名格式:字母数字与连字符的点分标签,挡注入/怪值。
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', 'unique:entry_domains,domain'],
            'node_id' => ['required', 'integer', Rule::exists('nodes', 'id')],
            'pointed_ip' => ['nullable', 'ip'],
            // 门牌 CNAME 到的线路池标签（选填）。`[!]` 必须与门牌不同 —— 自指是 CNAME 环。
            'cname_target' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', 'different:domain'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $node = Node::findOrFail($data['node_id']);

        // `[!!]` 这里【曾经】有一道 `abort_unless($node->forwards(), 422, ...)`,
        // 理由写的是"挂到落地没意义"。那句是错的,它把 SoCloud 的拓扑(入口域名
        // 恰好挂在中转上)当成了机制的定义。门牌解决的是"订阅发的是会变的 IP",
        // 与转不转发无关 —— 直连落地的 IP 被墙时,问题和代价完全一样。
        //
        // 订阅端 SubscriptionService 早已按"不限角色"实现(那次只修了下游、
        // 没回头修这里),于是管理端建不了、订阅端却准备好发 —— 两边口径对不上,
        // 表现是 L-19 永远配不上。别再加回来。
        $ed = EntryDomain::create($data + ['status' => 'standby']);
        audit('entry_domain.create', "新增入口域名「{$ed->domain}」→ 节点「{$node->name}」", $ed);

        return redirect('/admin/entry-domains')->with('status', "已新增 {$ed->domain}（备用）。设为在用后订阅才会发它。");
    }

    /** 设为在用：本域名成为该中转订阅对外发的入口,同中转其余降为备用。 */
    /**
     * 把这一条设为该节点【唯一】的在用域名。
     *
     * `[!!]` Node::activeEntryDomain() 用的是 where('status','active')->first(),
     * 注释声称"一台中转至多一个 active" —— 那个不变式【只能靠这里维护】。
     * 破掉之后 first() 按插入顺序返回,订阅发的是哪个域名变成不确定的。
     * [D] 2026-09-24 实测过:rotate() 曾直接置 active 而不降其它,
     *     轮换备用域名后同节点出现两个 active,而订阅仍发旧的那个 ——
     *     管理员以为"轮换完就顶上了",实际什么都没变。
     */
    private function makeSoleActive(EntryDomain $d): void
    {
        EntryDomain::where('node_id', $d->node_id)
            ->where('id', '!=', $d->id)->where('status', 'active')
            ->update(['status' => 'standby']);
        $d->update(['status' => 'active']);
    }

    /** 该节点此刻还有没有在用的入口域名 —— 没有的话订阅会回退发裸 IP。 */
    private function fallbackWarning(EntryDomain $d): string
    {
        $still = EntryDomain::where('node_id', $d->node_id)->where('status', 'active')->exists();

        return $still ? '' : '　⚠ 该节点现在【没有在用的入口域名】，订阅会回退到发节点裸 IP'
            .'（IP 被墙时只能改节点配置 + 等客户端更新）。请把备用域名「设为在用」。';
    }

    public function activate(EntryDomain $entryDomain)
    {
        $this->makeSoleActive($entryDomain);
        audit('entry_domain.activate', "入口域名「{$entryDomain->domain}」设为在用", $entryDomain);

        return back()->with('status', "{$entryDomain->domain} 已设为在用。订阅从此发它,别忘了 "
            .$entryDomain->dnsRecordHost().' 的 A 记录要指向中转当前 IP。');
    }

    /** 标记被墙:仅改状态、不动订阅(在用被墙时你去轮换 IP 或切备用域名)。 */
    public function block(EntryDomain $entryDomain)
    {
        $entryDomain->update(['status' => 'blocked']);
        audit('entry_domain.block', "入口域名「{$entryDomain->domain}」标记被墙", $entryDomain);

        return back()->with('status', "{$entryDomain->domain} 已标记被墙。去『轮换IP』换后端 IP,或把备用域名『设为在用』。"
            .$this->fallbackWarning($entryDomain));
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
        ]);
        // `[!!]` 必须走 makeSoleActive:直接置 active 会让同节点出现两个在用域名,
        //   而 activeEntryDomain() 取 first() —— 订阅可能仍在发旧的那个。
        $this->makeSoleActive($entryDomain);

        // `[!!]` 共用同一个 CNAME 标签的其它门牌,在 DNS 上会跟着一起变 ——
        // 它们的 pointed_ip 必须同步,否则面板会亮出一批【假的】「该改 DNS」告警,
        // 而真正要改的只有标签那一条 A 记录。只同步指向,不动 status:
        // 被墙的门牌不该因为别人轮换就自动复活。
        $siblings = 0;
        if ($entryDomain->isLayered()) {
            $siblings = EntryDomain::where('cname_target', $entryDomain->cname_target)
                ->where('id', '!=', $entryDomain->id)
                ->update(['pointed_ip' => $data['pointed_ip'], 'last_rotated_at' => now()]);
        }

        audit('entry_domain.rotate', "入口域名「{$entryDomain->domain}」轮换指向 {$data['pointed_ip']}"
            .($siblings > 0 ? "（同标签「{$entryDomain->cname_target}」的另 {$siblings} 个门牌一并同步）" : ''), $entryDomain);

        $host = $entryDomain->dnsRecordHost();

        return back()->with('status', "已登记:去 DNS 服务商把 {$host} 的 A 记录改成 {$data['pointed_ip']}（面板不会替你改）。"
            .($entryDomain->isLayered() ? " {$entryDomain->domain} 是 CNAME 到它的,不用动。" : ''));
    }

    public function destroy(EntryDomain $entryDomain)
    {
        $name = $entryDomain->domain;
        $wasActive = $entryDomain->status === 'active';
        $entryDomain->delete();
        audit('entry_domain.delete', "删除入口域名「{$name}」".($wasActive ? '（原为在用）' : ''));

        // `[!]` 删掉在用的那条,订阅会【静默】回退到发裸 IP —— 必须说出来。
        //   删除本身是合法操作(域名到期等),所以不拦,只把后果讲清楚。
        return back()->with('status', "已删除 {$name}".$this->fallbackWarning($entryDomain));
    }
}
