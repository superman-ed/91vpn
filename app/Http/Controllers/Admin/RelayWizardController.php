<?php

namespace App\Http\Controllers\Admin;

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 中转链路向导：选一台中转 + 一台落地 + 一个端口，一次把整条链路配好。
 *
 * [!!] 它替人做掉的那一步是【打开落地的 accept_proxy】。
 * 手工配的时候这一步要跑到另一个页面去做，而漏了的后果是：
 * 中转发 PROXY 头、落地不收，**两端都不报错**，只有客户端连不上
 * （见 compatibility/b2-reality-through-relay.md §2.5）。
 * 这是整套中转配置里最容易漏、且最难查的一步 —— 所以由向导保证它成对。
 *
 * [!] 向导只覆盖最常见的一种形态：裸端口转发、单出站、直连落地。
 * 更复杂的（多出站、备池、解协议入站、级联中转）仍走完整的规则表单 ——
 * 向导的价值在于把常见路径做对，不在于覆盖所有路径。
 */
class RelayWizardController extends \App\Http\Controllers\Controller
{
    public function create()
    {
        return view('admin.rules.wizard', [
            'relays' => Node::whereIn('role', Node::RELAY_ROLES)->orderBy('name')->get(),
            'landings' => Node::whereIn('role', ['landing', 'both'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'relay_id' => ['required', 'integer', 'exists:nodes,id'],
            'landing_id' => ['required', 'integer', 'exists:nodes,id'],
            'listen_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'send_proxy' => ['nullable', 'boolean'],
        ]);

        $relay = Node::findOrFail($data['relay_id']);
        $landing = Node::findOrFail($data['landing_id']);
        $send = $request->boolean('send_proxy');

        if (! in_array($relay->role, Node::RELAY_ROLES, true)) {
            throw ValidationException::withMessages(['relay_id' => '这台不是中转角色的节点']);
        }
        if (! $landing->needsUsers()) {
            throw ValidationException::withMessages(['landing_id' => '这台不是落地角色的节点']);
        }
        // [!] 落地必须有端口:中转要拨过去。role 对但 port=0 的落地是配错了。
        if ((int) $landing->port <= 0) {
            throw ValidationException::withMessages(['landing_id' => '这台落地没有端口，先去节点页补上']);
        }
        // [!!] 同一台中转上两条规则抢同一个端口 —— 节点侧的表现是后一条起不来,
        // 而面板这边看着两条都"已启用"。在这里挡住。
        if ($this->portTaken($relay->id, (int) $data['listen_port'])) {
            throw ValidationException::withMessages([
                'listen_port' => "中转「{$relay->name}」上已经有规则在用这个端口了",
            ]);
        }

        $rule = ForwardRule::create([
            'name' => "{$relay->name} → {$landing->name}",
            'enabled' => true,
            'inbound_type' => 'direct',
            'inbound_node_set' => [$relay->id],
            'listen_port' => (string) $data['listen_port'],
            'balance' => 'roundrobin',
            'backup_balance' => 'fallback',
            'hc_enabled' => true,
            'hc_interval_sec' => 30,
            'hc_max_fail' => 3,
            'hc_max_success' => 2,
        ]);

        ForwardOutbound::create([
            'rule_id' => $rule->id,
            'pool' => 'primary',
            'enabled' => true,
            'weight' => 1,
            'out_type' => 'direct',
            'target_addr' => $landing->server,
            'target_port' => (string) $landing->port,
            'send_proxy_protocol' => $send ? 2 : 0,
            'trusted_transit' => true,
        ]);

        // [!!] 关键的一步:中转发头,落地就必须收头。这里直接替他打开 ——
        // 手工配时这一步在另一个页面,漏了两端都不报错。
        $flipped = false;
        if ($send && ! $landing->accept_proxy_protocol) {
            $landing->update(['accept_proxy_protocol' => true]);
            $flipped = true;
            audit('node.update', "向导替落地「{$landing->name}」打开了 accept_proxy（配对中转发头）", $landing);
        }

        Audit::log('rule.create', 'rule', $rule->id, $rule->name, [], $rule->fresh()->getAttributes());

        $msg = "已创建「{$rule->name}」：用户连 {$relay->server}:{$data['listen_port']}，转给 {$landing->server}:{$landing->port}。";
        $msg .= '节点会在下一个拉取周期取走这条规则。';

        // [!!] 只要这条链路发 PROXY 头,落地就在收头,就【必须】只放行中转 IP ——
        // PROXY 头无认证,能连到落地那个端口的人可随意伪造来源 IP。
        // 每次发头都提醒(不只在"这次刚翻开"时):给已开收头的落地再加一个中转,
        // 新中转的 IP 同样得进白名单,否则要么被挡、要么运维根本不知道要加。
        if ($send) {
            $flipNote = $flipped
                ? "已自动打开落地「{$landing->name}」的 PROXY 头收取。"
                : "落地「{$landing->name}」此前已在收 PROXY 头。";
            // 列出当前所有会拨向这台落地的中转 IP(含刚建的这条),它们都得放行。
            $ips = $this->frontingRelayIps($landing);
            $ipList = $ips === [] ? $relay->server : implode('、', $ips);
            $msg .= " {$flipNote} ⚠️ 开了收头的落地【必须把 {$landing->port} 端口只放行中转 IP】"
                .'(PROXY 头没有认证,不锁的话任何人都能伪造来源 IP)。'
                ."当前应放行的中转:{$ipList}。"
                .'最省事的做法:到【节点管理】对这台落地点【🚀部署】重装一次 ——'
                .'一键部署会按拓扑自动配好防火墙,不用手敲 iptables。';
        }

        return redirect('/admin/rules')->with('status', $msg);
    }

    /**
     * 当前会拨向这台落地的所有中转的 server IP(含按地址:端口 与 按节点集两种写法)。
     * 落地开了 accept_proxy 后,这些就是防火墙该放行的全部来源。
     *
     * @return array<int,string>
     */
    private function frontingRelayIps(Node $landing): array
    {
        $ips = [];
        foreach (ForwardRule::with('outbounds')->where('enabled', true)->get() as $r) {
            $targets = $r->outbounds->contains(function ($o) use ($landing) {
                if (in_array((int) $landing->id, array_map('intval', (array) ($o->target_node_set ?? [])), true)) {
                    return true;
                }

                return (string) $o->target_addr === (string) $landing->server
                    && (string) $o->target_port === (string) $landing->port;
            });
            if (! $targets) {
                continue;
            }
            foreach ((array) ($r->inbound_node_set ?? []) as $rid) {
                $n = Node::find((int) $rid);
                if ($n && $n->server !== '' && $n->server !== null) {
                    $ips[$n->server] = true;
                }
            }
        }

        return array_keys($ips);
    }

    /** 这台中转上有没有别的规则已经在用这个端口。 */
    private function portTaken(int $relayId, int $port): bool
    {
        foreach (ForwardRule::where('enabled', true)->get() as $r) {
            if (! in_array($relayId, (array) ($r->inbound_node_set ?? []), false)) {
                continue;
            }
            foreach (preg_split('/[,\s]+/', (string) $r->listen_port) as $spec) {
                $spec = trim($spec);
                if ($spec === '') {
                    continue;
                }
                [$lo, $hi] = array_pad(explode('-', $spec, 2), 2, null);
                $lo = (int) $lo;
                $hi = $hi === null ? $lo : (int) $hi;
                if ($port >= $lo && $port <= $hi) {
                    return true;
                }
            }
        }

        return false;
    }
}
