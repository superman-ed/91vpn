# 04 · 节点部署

## 1. 一键部署（推荐）

后台 → 节点管理 → 该节点行的「部署」按钮。填 SSH 主机、端口、用户，
以及**私钥正文或密码**。

流程：面板 SSH 进目标机 → 目标机自己 `curl` 下载 `install.sh` 与二进制 →
装 systemd → `/ready` 验收。

`[!]` 凭据只走 **stdin**，不进命令行、不落盘，进程结束即消失。
面板只记录"装到哪台、结果如何、日志"，**绝不存 SSH 私钥或密码**。

`[!]` `host_key` 是首次连上时对端的 SSH 指纹（TOFU）。下次部署同一台若指纹变了
会告警 —— 换机或中间人的信号。所以部署记录**只清日志正文、不删行**。

### 前置：二进制要能被下载

目标机自己去 `<base_url>/agent-linux-<arch>` 和 `<base_url>/install.sh` 拉。
把这两个文件放到面板 `public/agent/v1/` 即可（该目录已在 `.gitignore` 里，
**不要把 24MB 的二进制提交进仓库**）。

```bash
cd agent && CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
  go build -trimpath -ldflags "-s -w" -o /tmp/agent-linux-amd64 ./cmd/agent
cp /tmp/agent-linux-amd64 deploy/install.sh <面板>/public/agent/v1/
```

## 2. 手工安装

```bash
bash install.sh \
  --panel sspanel-uim \
  --api-url https://app.<域名> \
  --node-id 12 \
  --api-key-file /root/secret \
  --server-type vmess \
  [--accept-proxy]              # 只收中转流量的落地才加，见 05
```

中转节点用另一组参数：

```bash
bash install.sh --relay-mode --forward-file /tmp/forward.json
```

`install.sh` 会：装二进制到 `/usr/local/bin/agent`、写 `/etc/agent/agent.conf`（0600）、
装 `agent.service`（`Restart=always`、开机自启）、按内存设 `GOMEMLIMIT`、
等 `/ready` 通过才算成功。

## 3. `agent.conf` 配置项

键值对，每行一个。与 soga 的配置文件同构（这是刻意的：便于从 soga 迁移）。

### 必填

| 键 | 说明 |
|---|---|
| `type` | 面板类型：`sspanel-uim` / `v2board` / `soga-v1` / … |
| `server_type` | `vmess` / `vless` / `trojan` / `shadowsocks` |
| `node_id` | 节点在面板里的 ID |
| `api` | `webapi`（HTTP）或 `db`（直连数据库） |
| `webapi_url` / `webapi_key` | `api=webapi` 时必填 |
| `db_host` / `db_port` / `db_name` / `db_user` / `db_password` | `api=db` 时必填 |

### 常用

| 键 | 默认 | 说明 |
|---|---|---|
| `check_interval` | 60 | 拉配置与用户的间隔（秒） |
| `submit_interval` | 60 | 上报流量与状态的间隔（秒） |
| `accept_proxy_protocol` | false | 入站收 PROXY 头（见 [05](05-relay.md)） |
| `user_speed_limit` / `speed_limit_unit` | 0 | 限速 |
| `forward_file` | | 中转模式的规则文件 |
| `relay_mode` | false | 纯中转（不连面板拉用户） |

`[!]` 观测端点的监听地址**不在配置文件里** —— 它是 `install.sh` 的
`--api-addr` 参数（默认 `127.0.0.1:9090`），最终落到 systemd 单元的
`-api` 启动参数上。

### 证书

| 键 | 说明 |
|---|---|
| `cert_mode` | `none` / `file` / `http` / `dns` |
| `cert_domain` / `cert_file` / `key_file` | |
| `dns_provider` + 该 provider 的环境变量 | `cert_mode=dns` 时 |

### 规则与地理库

`block_list_file` · `white_list_file` · `routes_file` · `dns_file` ·
`geosite_file` · `geoip_file`

`[!]` 完整清单以 `agent/internal/config/config.go` 为准。
遇到未实现但 soga 支持的键，agent 会**明确报错**而不是静默忽略
（`sogakeys.go` 维护这份名单）—— 静默忽略会让人以为配置生效了。

## 4. 验收

```bash
systemctl is-enabled agent && systemctl is-active agent   # enabled / active
curl -s 127.0.0.1:9090/health                             # 进程活着
curl -s 127.0.0.1:9090/ready                              # 200 = 可服务
curl -s 127.0.0.1:9090/metrics | head                     # 指标
ss -tlnp | grep agent                                     # 监听端口
```

`[!!]` `/health` 与 `/ready` 不是一回事：进程活着（health）不代表能服务（ready）。
REALITY 节点的 dest 挂掉时，端口照常监听、`/health` 照常 200，
但**没有任何客户端能完成握手** —— 那种情况下 `/ready` 会失败。

## 5. 升级

重跑一次一键部署即可（install.sh 是幂等的，默认**保留已有配置**，
要覆盖用 `--force-conf`）。
