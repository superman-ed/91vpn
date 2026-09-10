<?php

namespace App\Services;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

/**
 * 一键部署的执行引擎(方案 A:面板 SSH 进目标机跑 install.sh)。
 *
 * 边界与安全:
 *  - [!!] 私钥/密码只在一次 run() 的生命周期里存在,来自调用方(命令读 stdin),
 *    本类不落盘、不入库、不写日志。用完即焚由进程结束保证。
 *  - [!!] 大二进制由【目标机自己 curl】(既定决策),本类只 SFTP 一个小文件
 *    forward.json,其余用一段 bash 脚本经 SSH 通道执行,输出实时回流。
 *  - [!] 主机指纹 TOFU:首连拿到对端指纹返回给调用方固定;第二次若变了,
 *    由调用方比对告警(中间人/被换机)。
 *
 * install.sh 的契约(见 sogacore/deploy/install.sh 的 --binary-url/--relay-mode):
 *  目标机: curl 到 install.sh → 它再 curl 二进制 → 装 systemd → /ready 验收。
 */
class Deployer
{
    /** 连接与握手超时(秒)。装机本身可能久,单独给更长的读超时。 */
    public int $connectTimeout = 15;

    /**
     * @param  array{host:string,port:int,user:string,private_key?:string,password?:string}  $creds
     * @param  array{base_url:string,forward_json:string,accept_proxy?:bool}  $spec
     * @param  callable(string):void  $onLine  每读到一段输出就回调(用于实时写库)
     * @return array{ok:bool,host_key:?string,agent_version:?string,reason:?string}
     */
    public function run(array $creds, array $spec, callable $onLine): array
    {
        // SSH2 走可选依赖;类不存在时给清楚的错误而不是 500。
        if (! class_exists(\phpseclib3\Net\SSH2::class)) {
            return $this->fail(null, 'phpseclib 未安装(composer require phpseclib/phpseclib)');
        }

        $host = $creds['host'];
        $port = (int) ($creds['port'] ?? 22);
        $user = $creds['user'] ?? 'root';

        $onLine("==> 连接 {$user}@{$host}:{$port}");

        $ssh = new \phpseclib3\Net\SSH2($host, $port, $this->connectTimeout);
        // [!] 关掉库对 exec 的行长限制,长命令不被截断。
        $ssh->setTimeout(0); // 0 = 读不设超时;装机可能几十秒

        $hostKey = null;
        try {
            $pub = $ssh->getServerPublicHostKey();
            $hostKey = $pub === false ? null : $this->fingerprint($pub);
        } catch (\Throwable $e) {
            return $this->fail(null, "拿不到对端主机指纹: {$e->getMessage()}");
        }
        if ($hostKey) {
            $onLine("==> 对端主机指纹 {$hostKey}");
        }

        // ---- 登录:优先私钥,其次密码 ----
        try {
            if (! empty($creds['private_key'])) {
                $key = PublicKeyLoader::load($creds['private_key']);
                $ok = $ssh->login($user, $key);
            } elseif (isset($creds['password'])) {
                $ok = $ssh->login($user, (string) $creds['password']);
            } else {
                return $this->fail($hostKey, '既没有私钥也没有密码');
            }
        } catch (\Throwable $e) {
            return $this->fail($hostKey, "SSH 登录异常: {$e->getMessage()}");
        }
        if (! $ok) {
            return $this->fail($hostKey, 'SSH 登录失败(用户名/私钥/密码不对,或该用户不被允许)');
        }
        $onLine('==> 登录成功');

        // ---- 1. SFTP 上传需要面板下发的敏感文件 ----
        // relay: forward.json;panel(落地): 91vpn 的 api-key(经文件喂 --api-key-file,不进 argv)。
        $mode = $spec['mode'] ?? 'relay';
        try {
            $sftp = new SFTP($host, $port, $this->connectTimeout);
            if (! empty($creds['private_key'])) {
                $sftp->login($user, PublicKeyLoader::load($creds['private_key']));
            } else {
                $sftp->login($user, (string) $creds['password']);
            }
            if ($mode === 'relay') {
                if (! $sftp->put('/tmp/agent-forward.json', $spec['forward_json'])) {
                    return $this->fail($hostKey, '上传 forward.json 失败');
                }
                $sftp->chmod(0600, '/tmp/agent-forward.json');
                $onLine('==> forward.json 已上传 (/tmp/agent-forward.json)');
            } else {
                if (! $sftp->put('/tmp/agent-apikey', (string) ($spec['api_key'] ?? ''))) {
                    return $this->fail($hostKey, '上传 api-key 失败');
                }
                $sftp->chmod(0600, '/tmp/agent-apikey');
                $onLine('==> 91vpn 凭据已上传 (/tmp/agent-apikey, 0600)');
            }
        } catch (\Throwable $e) {
            return $this->fail($hostKey, "SFTP 上传异常: {$e->getMessage()}");
        }

        // ---- 2. 跑装机脚本,输出实时回流 ----
        $script = $this->buildScript($spec, $user);
        try {
            $ssh->exec($script, fn ($chunk) => $this->emitLines($chunk, $onLine));
        } catch (\Throwable $e) {
            return $this->fail($hostKey, "执行装机脚本异常: {$e->getMessage()}");
        }

        $rc = $ssh->getExitStatus();
        if ($rc !== 0) {
            return $this->fail($hostKey, "装机脚本退出码 {$rc}(见上方日志)");
        }

        // 版本行由脚本用 "AGENT_VERSION=" 前缀打印,顺手抓一下。
        return ['ok' => true, 'host_key' => $hostKey, 'agent_version' => null, 'reason' => null];
    }

    /**
     * 渲染在目标机上执行的 bash 脚本(纯函数,可单测)。
     *
     * 决策落地:目标机自己 curl install.sh 与二进制;架构在机上判;
     * install.sh 用 --binary-url + (relay: --relay-mode/--forward-file |
     * panel: --panel/--api-url/--node-id/--api-key-file/--server-type) 契约。
     */
    /**
     * 把远端一段输出按行喂给 $onLine,并告诉 phpseclib【继续读】。
     *
     * [!!] 返回值不是"成功与否",而是"要不要中止":phpseclib 的 exec 回调
     * 返回 true 会让它 close_channel 并立刻返回(见 Net/SSH2.php 的
     * `if ($callback($temp) === true) { $this->close_channel(...); return true; }`)。
     *
     * 这里原来 `return true`,于是每次部署都在远端吐出【第一行】之后就被掐断:
     * 日志停在 "==> 目标机架构 x86_64",退出码拿不到(getExitStatus() 返回 false),
     * 报成"装机脚本退出码 (空)"。也就是说一键部署【从来没有成功过一次】——
     * 而 deploy_runs 一直是空表,没人发现。
     *
     * 返回 false/null = 继续读。别改成 true。
     */
    public function emitLines(string $chunk, callable $onLine): bool
    {
        // 对端一段可能含多行;按行切,保留 install.sh 的分行观感。
        foreach (preg_split("/\r\n|\r|\n/", rtrim($chunk, "\r\n")) as $l) {
            if ($l !== '') {
                $onLine($l);
            }
        }

        return false;
    }

    public function buildScript(array $spec, string $user = 'root'): string
    {
        $base = rtrim((string) $spec['base_url'], '/');
        $sudo = $user === 'root' ? '' : 'sudo -n ';
        $mode = $spec['mode'] ?? 'relay';

        // 已被控制器校验(base_url 限 http(s);其余字段限白名单/数字/URL),
        // 仍统一单引号包裹,避免壳解释。
        $q = fn ($v) => "'" . str_replace("'", "'\\''", (string) $v) . "'";
        $b = $q($base);
        $acceptProxy = ! empty($spec['accept_proxy']) ? " \\\n  --accept-proxy" : '';

        if ($mode === 'panel') {
            $installArgs = "--panel {$q($spec['panel_type'] ?? 'sspanel-uim')}"
                . " \\\n  --api-url {$q($spec['api_url'] ?? '')}"
                . " \\\n  --node-id {$q((int) ($spec['node_id'] ?? 0))}"
                . " \\\n  --api-key-file /tmp/agent-apikey"
                . " \\\n  --server-type {$q($spec['server_type'] ?? 'vmess')}";
            $cleanup = '/tmp/agent-install.sh /tmp/agent-apikey';
            $firewall = $this->firewallSnippet($spec, $sudo);
        } else {
            $installArgs = "--relay-mode \\\n  --forward-file /tmp/agent-forward.json";
            $cleanup = '/tmp/agent-install.sh /tmp/agent-forward.json';
            $firewall = '';
        }

        // [!] 这段经登录 shell 执行(可能是 dash),只用 POSIX 语法——不加 pipefail
        //     等 bash 专属项;真正的装机逻辑用 `bash install.sh` 显式跑,不受影响。
        return <<<BASH
BASE={$b}
echo "==> 目标机架构 \$(uname -m)"
case "\$(uname -m)" in
  x86_64|amd64) A=amd64 ;;
  aarch64|arm64) A=arm64 ;;
  *) echo "不支持的架构 \$(uname -m),放弃"; exit 22 ;;
esac
BIN_URL="\$BASE/agent-linux-\$A"
INSTALL_URL="\$BASE/install.sh"
echo "==> 拉取 install.sh: \$INSTALL_URL"
curl -fsSL "\$INSTALL_URL" -o /tmp/agent-install.sh || { echo "拉取 install.sh 失败"; exit 21; }
echo "==> 交给 install.sh(二进制来源 \$BIN_URL)"
{$sudo}bash /tmp/agent-install.sh \\
  --binary-url "\$BIN_URL" \\
  {$installArgs}{$acceptProxy} 2>&1
rc=\$?
rm -f {$cleanup}
if [ "\$rc" != 0 ]; then echo "==> install.sh 退出码 \$rc"; exit \$rc; fi
{$firewall}echo "==> install.sh 退出码 \$rc"
exit \$rc
BASH;
    }

    /**
     * accept_proxy 落地的防火墙片段:只放行中转源 IP 连落地入站端口,其余 DROP。
     *
     * [!!] PROXY protocol 头【无认证】:不锁源 IP,任何人都能伪造客户端真实 IP。
     *   开了 accept_proxy 的落地必须只收中转的流量。
     * [!] 严格只碰这个 TCP 端口,绝不动 22/established —— 配错也锁不死 SSH。
     *   规则用 -C 判重再 -I/-A,可重复部署不叠加。
     * [!] iptables 规则默认不持久(重启失效),没装 persistent 时脚本会提示。
     */
    private function firewallSnippet(array $spec, string $sudo): string
    {
        $port = (int) ($spec['proxy_port'] ?? 0);
        $srcs = array_values(array_filter((array) ($spec['allow_src'] ?? [])));
        if (empty($spec['accept_proxy']) || $port <= 0 || $srcs === []) {
            // 开了 accept_proxy 却没给端口/源,必须显眼告警(裸奔风险)。
            if (! empty($spec['accept_proxy'])) {
                return "echo \"⚠️ 已开 accept_proxy 但未提供落地端口或中转源 IP —— 未配防火墙!PROXY 头可被伪造,请手动只放行中转源 IP。\"\n";
            }

            return '';
        }
        $ipList = implode(' ', array_map(fn ($ip) => "'" . str_replace("'", '', $ip) . "'", $srcs));

        return <<<FW
echo "==> 配置防火墙:仅放行中转源 IP 连入站 {$port}/tcp,其余 DROP"
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | head -1 | grep -qi active; then
  # [!!] 机器归 ufw 管时【必须走 ufw】。直接往 INPUT 链尾 -A 一条 DROP 是无效的:
  #   ufw 的 ufw-before-input 等跳转排在前面,包在那里就被 ACCEPT 了,永远走不到链尾。
  #   实测:目标机 ufw 放行了 39000:40000/tcp,追加的 DROP 形同虚设 ——
  #   而面板日志照样打印"其余 DROP",看起来配好了,其实端口对全世界敞着。
  # [!] 用 insert 1 而不是追加:ufw 按顺序匹配,已有的宽松放行(如 39000:40000)
  #   排在前面就会先命中。先插 deny,再把 allow 插到它前面,得到
  #   [allow 中转源...] [deny 全部] [原有宽松规则...]。
  {$sudo}ufw --force delete deny proto tcp from any to any port {$port} >/dev/null 2>&1 || true
  {$sudo}ufw --force insert 1 deny proto tcp from any to any port {$port} >/dev/null
  for ip in {$ipList}; do
    {$sudo}ufw --force delete allow proto tcp from "\$ip" to any port {$port} >/dev/null 2>&1 || true
    {$sudo}ufw --force insert 1 allow proto tcp from "\$ip" to any port {$port} >/dev/null
  done
  echo "==> ufw 规则(前 6 条):"
  {$sudo}ufw status numbered 2>/dev/null | sed -n '4,9p'
else
  # 裸 iptables:同样要注意顺序 —— 先插 DROP,再把 ACCEPT 插到它【前面】。
  {$sudo}iptables -C INPUT -p tcp --dport {$port} -j DROP 2>/dev/null || {$sudo}iptables -I INPUT -p tcp --dport {$port} -j DROP
  for ip in {$ipList}; do
    {$sudo}iptables -C INPUT -p tcp --dport {$port} -s "\$ip" -j ACCEPT 2>/dev/null || {$sudo}iptables -I INPUT -p tcp --dport {$port} -s "\$ip" -j ACCEPT
  done
  command -v netfilter-persistent >/dev/null 2>&1 && {$sudo}netfilter-persistent save >/dev/null 2>&1 || echo "==> 提示:iptables 规则未持久化,重启会失效。装 iptables-persistent 后 netfilter-persistent save。"
fi

FW;
    }

    /** 把对端主机公钥转成可读指纹(sha256:base64,同 OpenSSH 风格)。 */
    private function fingerprint(string $publicKey): string
    {
        // getServerPublicHostKey 返回 "ssh-ed25519 AAAA..." 形式;取 base64 段算 sha256。
        $parts = explode(' ', trim($publicKey));
        $blob = isset($parts[1]) ? base64_decode($parts[1], true) : false;
        if ($blob === false) {
            return 'sha256:' . substr(hash('sha256', $publicKey), 0, 43);
        }

        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob, true)), '=');
    }

    private function fail(?string $hostKey, string $reason): array
    {
        return ['ok' => false, 'host_key' => $hostKey, 'agent_version' => null, 'reason' => $reason];
    }
}
