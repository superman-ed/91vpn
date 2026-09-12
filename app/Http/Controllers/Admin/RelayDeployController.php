<?php

namespace App\Http\Controllers\Admin;

use App\Models\DeployRun;
use App\Models\Node;
use Illuminate\Http\Request;

/**
 * 一键部署(方案 A:面板 SSH 进目标机跑 install.sh)。
 *
 * [!!] 私钥/密码不入库、不落盘、不进 argv。表单 POST 过来后,只经 stdin
 *   管道喂给脱离出去的后台进程(deploy:run),写完即关。用完即焚由那个进程
 *   结束保证(见 DeployRun 命令)。
 *
 * [!] 这一层在 web 中间件里(auth+admin+CSRF),且整个面板只绑 127.0.0.1;
 *   私钥只在"本机浏览器 → 本机面板 → 本机子进程"之间流动,不出这台机器。
 */
class RelayDeployController extends \App\Http\Controllers\Controller
{
    public function start(Request $request, Node $node)
    {
        $isLanding = $node->role === 'landing';
        // 支持:中转类(relay 模式)与落地(panel 模式)。其余角色不支持。
        abort_unless($node->forwards() || $isLanding, 422, "节点角色 {$node->role} 不支持一键部署");

        $rules = [
            'ssh_host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['nullable', 'string', 'max:64'],
            'auth_mode' => ['required', 'in:key,password'],
            'secret' => ['required', 'string', 'max:20000'],   // SSH 私钥正文或密码
            // base_url 会被拼进目标机脚本,严格限定 http(s),挡命令注入/怪协议。
            'base_url' => ['required', 'url', 'starts_with:https://,http://', 'max:500'],
            'accept_proxy' => ['nullable', 'boolean'],
        ];
        if ($isLanding) {
            // 落地要连 91vpn:身份字段必填;server-type/panel-type 限白名单。
            $rules += [
                'api_url' => ['required', 'url', 'starts_with:https://,http://', 'max:255'],
                'node_id' => ['required', 'integer', 'min:1'],
                'api_key' => ['required', 'string', 'max:200'],          // 91vpn 节点 secret
                'server_type' => ['required', 'in:vmess,vless,trojan,shadowsocks'],
                'panel_type' => ['nullable', 'in:sspanel-uim,soga-v1,v2board,xboard,ppanel,proxypanel,whmcs,v2raysocks'],
                'proxy_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                // 中转源 IP 列表(逗号分隔);逐个 IP 校验,挡注入。
                'allow_src' => ['nullable', 'string', 'max:2000'],
            ];
        }
        $data = $request->validate($rules);

        if ($isLanding && ! empty($data['allow_src'])) {
            foreach (array_filter(array_map('trim', explode(',', $data['allow_src']))) as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                    return response()->json(['message' => "中转源 IP 不合法: {$ip}"], 422);
                }
            }
        }

        $run = DeployRun::create([
            'node_id' => $node->id,
            'status' => 'pending',
            'ssh_host' => $data['ssh_host'],
            'ssh_port' => $data['ssh_port'] ?? 22,
            'ssh_user' => $data['ssh_user'] ?: 'root',
            'created_by' => optional($request->user())->id,
        ]);

        audit('node.deploy', "节点 {$node->name} 部署", $node);

        $this->spawn($run, $data);

        return response()->json(['run_id' => $run->id]);
    }

    /** 轮询:实时状态 + 日志。 */
    public function log(Node $node, DeployRun $run)
    {
        abort_unless($run->node_id === $node->id, 404);

        return response()->json([
            'status' => $run->status,
            'running' => $run->running(),
            'log' => (string) $run->log,
            'reason' => $run->reason,
            'agent_version' => $run->agent_version,
        ]);
    }

    /**
     * 落地部署要填的 91vpn 身份 —— 面板本身就是 91vpn,这几项它全知道,不必手粘。
     *
     * [!] secret 按需拉取(点开部署弹窗时才取这一台的),不把所有节点的 secret
     *   洒进节点列表页的 DOM —— 和"secret 不乱放"一贯做法一致。仅 admin 组可达。
     */
    public function identity(Node $node)
    {
        return response()->json([
            'api_url' => (string) config('app.url'),   // 用户面/mod_mu 的对外地址,节点身份无关机器
            'node_id' => $node->id,
            'server_type' => $node->type,
            'secret' => $node->secret,
        ]);
    }

    /**
     * spawn 一个【脱离本请求】的后台进程跑 deploy:run,凭据经 stdin 喂进去。
     *
     * [!] setsid 让子进程进新会话:php-fpm 请求结束后它继续活着,不被回收。
     *   stdin 用管道(不是文件/argv)——凭据不落盘、不进进程列表。
     */
    private function spawn(DeployRun $run, array $data): void
    {
        $php = PHP_BINDIR . '/php';                 // 本镜像 php cli 在 /usr/local/bin/php
        if (! is_executable($php)) {
            $php = 'php';
        }
        // 非密参数走 argv(可进 ps,无所谓);密钥(SSH+91vpn)走 stdin。
        $cmd = ['setsid', $php, base_path('artisan'), 'deploy:run', (string) $run->id,
            '--base-url=' . $data['base_url']];
        if (! empty($data['accept_proxy'])) {
            $cmd[] = '--accept-proxy';
        }
        if (($data['api_url'] ?? null) !== null) {  // 落地(panel 模式)才有这些
            $cmd[] = '--api-url=' . $data['api_url'];
            $cmd[] = '--node-id=' . (int) $data['node_id'];
            $cmd[] = '--server-type=' . $data['server_type'];
            if (! empty($data['panel_type'])) {
                $cmd[] = '--panel-type=' . $data['panel_type'];
            }
            if (! empty($data['proxy_port'])) {
                $cmd[] = '--proxy-port=' . (int) $data['proxy_port'];
            }
            if (! empty($data['allow_src'])) {
                $cmd[] = '--allow-src=' . $data['allow_src'];
            }
        }

        $descriptors = [
            0 => ['pipe', 'r'],                     // stdin:喂密钥(JSON)
            1 => ['file', '/dev/null', 'a'],        // 输出全进 deploy_runs.log,这里丢弃
            2 => ['file', '/dev/null', 'a'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, base_path());
        if (! is_resource($proc)) {
            $run->markFailed('无法启动后台部署进程(proc_open 失败)');

            return;
        }

        // 密钥经 stdin(JSON):auth_mode + ssh_secret + (落地)91vpn 的 api_key。
        // 写完立刻关,子进程读到 EOF 即开工。都不进 argv/ps。
        fwrite($pipes[0], json_encode([
            'auth_mode' => $data['auth_mode'],
            'ssh_secret' => $data['secret'],
            'api_key' => $data['api_key'] ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fclose($pipes[0]);

        // [!] 不 proc_close —— 那会阻塞到子进程结束(30-60s),把 HTTP 请求拖死。
        //   setsid 已让它独立存活;这里立即返回,让页面去轮询日志。
    }
}
