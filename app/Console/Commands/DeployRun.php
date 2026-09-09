<?php

namespace App\Console\Commands;

use App\Models\DeployRun as DeployRunModel;
use App\Models\Node;
use App\Services\Deployer;
use App\Services\ForwardRuleService;
use Illuminate\Console\Command;

/**
 * 后台执行一次一键部署。由控制器 spawn 成脱离进程调用:
 *
 *   php artisan deploy:run {runId} --base-url=... [--accept-proxy]
 *
 * [!!] SSH 凭据【只从 stdin 读】,不走参数(argv 会进 ps/历史)、不落盘。
 *   首行 = 认证方式: "key" 或 "password";其后到 EOF 全是私钥/密码正文。
 *   进程结束凭据即随内存消失 —— 这就是"用完即焚"的兑现。
 *
 * 输出:全程把 install.sh 的输出实时 append 进 deploy_runs.log,页面轮询即见。
 */
class DeployRun extends Command
{
    protected $signature = 'deploy:run {run : deploy_runs.id}
        {--base-url= : agent 二进制与 install.sh 的下载根地址}
        {--accept-proxy : 入站开启 PROXY protocol(仅收中转流量的节点)}
        {--api-url= : (落地)91vpn 面板地址}
        {--node-id= : (落地)该节点在 91vpn 的 node id}
        {--server-type=vmess : (落地)协议 vmess|vless|trojan...}
        {--panel-type=sspanel-uim : (落地)面板类型}
        {--proxy-port= : (落地,开 accept_proxy 时)落地入站端口,配防火墙用}
        {--allow-src= : (落地,开 accept_proxy 时)允许的中转源 IP,逗号分隔}';

    protected $description = '执行一次节点一键部署(SSH → install.sh),供控制器后台调用';

    public function handle(Deployer $deployer, ForwardRuleService $compiler): int
    {
        $run = DeployRunModel::find((int) $this->argument('run'));
        if (! $run) {
            $this->error('找不到该部署记录');

            return 1;
        }
        // 只跑一次:已终结的记录不再执行(防重复 spawn)。
        if (! $run->running()) {
            $this->error("部署记录状态为 {$run->status},不再执行");

            return 1;
        }

        $node = Node::find($run->node_id);
        if (! $node) {
            $run->markFailed('目标节点已不存在');

            return 1;
        }

        // ---- 读凭据(stdin,一次性,JSON)----
        // {auth_mode:key|password, ssh_secret:"...", api_key?:"91vpn 节点 secret(落地用)"}
        $raw = stream_get_contents(STDIN);
        if ($raw === false || trim($raw) === '') {
            $run->markFailed('未从 stdin 收到凭据');

            return 1;
        }
        $in = json_decode($raw, true);
        unset($raw); // 尽早松手
        if (! is_array($in)) {
            $run->markFailed('stdin 凭据不是合法 JSON');

            return 1;
        }
        $authMode = (string) ($in['auth_mode'] ?? '');
        $sshSecret = (string) ($in['ssh_secret'] ?? '');
        $apiKey = (string) ($in['api_key'] ?? '');

        $creds = [
            'host' => $run->ssh_host,
            'port' => (int) $run->ssh_port,
            'user' => $run->ssh_user,
        ];
        if ($authMode === 'key') {
            $creds['private_key'] = $sshSecret;
        } elseif ($authMode === 'password') {
            $creds['password'] = $sshSecret;
        } else {
            $run->markFailed("未知认证方式: {$authMode}(应为 key 或 password)");

            return 1;
        }

        $run->markRunning();

        // ---- 按角色决定部署模式:中转=relay(本地规则) / 落地=panel(连 91vpn)----
        $baseUrl = (string) $this->option('base-url');
        $acceptProxy = (bool) $this->option('accept-proxy');

        if ($node->forwards()) {
            // 中转:编译本节点该拿的转发规则(信封同 GET .../routes)。
            $spec = [
                'mode' => 'relay',
                'base_url' => $baseUrl,
                'accept_proxy' => $acceptProxy,
                'forward_json' => json_encode(
                    ['ret' => 1, 'data' => $compiler->compileForNode($node)],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                ),
            ];
        } elseif ($node->role === 'landing') {
            // 落地:走 install.sh 面板模式,连 91vpn 拉 reality/accept_proxy 配置。
            if ($apiKey === '') {
                $run->markFailed('落地部署缺 91vpn 节点 secret(api_key)');

                return 1;
            }
            $allowSrc = array_values(array_filter(array_map(
                'trim', explode(',', (string) $this->option('allow-src'))
            )));
            $spec = [
                'mode' => 'panel',
                'base_url' => $baseUrl,
                'accept_proxy' => $acceptProxy,
                'panel_type' => (string) $this->option('panel-type'),
                'api_url' => (string) $this->option('api-url'),
                'node_id' => (int) $this->option('node-id'),
                'server_type' => (string) $this->option('server-type'),
                'api_key' => $apiKey,
                'proxy_port' => (int) $this->option('proxy-port'),
                'allow_src' => $allowSrc,
            ];
        } else {
            $run->markFailed("节点角色 {$node->role} 不支持一键部署");

            return 1;
        }

        // ---- 跑 ----
        $result = $deployer->run($creds, $spec, function (string $line) use ($run) {
            $run->appendLog($line);
        });

        // ---- TOFU:指纹变了要显眼告警(接管/换机信号)----
        // 拿【本节点上一条已记录指纹的部署】来比,不给 nodes 加列;指纹留在 deploy_runs。
        if ($result['host_key']) {
            $prior = DeployRunModel::where('node_id', $node->id)
                ->where('id', '!=', $run->id)
                ->whereNotNull('host_key')
                ->orderByDesc('id')
                ->value('host_key');
            if ($prior && $prior !== $result['host_key']) {
                $run->appendLog("⚠️ 主机指纹变了:上次 {$prior},这次 {$result['host_key']} —— 可能被换机/中间人,请核实!");
            }
        }

        if ($result['ok']) {
            $run->appendLog('✅ 部署完成,节点已就绪');
            $run->markOk($result['agent_version'] ?? null);

            return 0;
        }

        $run->appendLog('❌ ' . ($result['reason'] ?? '部署失败'));
        $run->markFailed($result['reason'] ?? '部署失败');

        return 1;
    }
}
