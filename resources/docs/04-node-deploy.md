# 04 · 节点部署 —— 一键 / 手工 / 配置项全表

> **读法**:零假设,每节先说**这步在干嘛**,命令齐 → ✅对了吗。纯文字。
> 真实值:面板 `app.91app.shop`、二进制源 `https://app.91app.shop/agent/v1`。
> 不懂词看 [00](00-glossary.md);第一次装节点建议先走 [02 · 快速开始](02-quickstart.md)。

---

## 开始前

- [ ] 面板已挂域名跑起来([03](03-panel-deploy.md)),`app.91app.shop` 能访问。
- [ ] agent 二进制已发布到面板(见下面「前置」),`curl -sI https://app.91app.shop/agent/v1/install.sh` 返 200。
- [ ] 已在后台建好这个节点、拿到它的 **secret**([02](02-quickstart.md) 第 7 步)。

---

## 前置 · 让二进制能被下载(装任何节点前都要有)

🎯 **这步在干嘛**:节点自己去 `<源>/agent-linux-<架构>` 和 `<源>/install.sh` 拉文件,先把它们放到面板的 `public/agent/v1/`。

**最省事**(在 sogacore 仓库机上):
```bash
bash tools/publish-agent.sh /path/to/91vpn      # 用 Docker 里的 Go 编译并放好,你不用装 Go
```
或手动编:
```bash
cd agent && CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
  go build -trimpath -ldflags "-s -w" -o /tmp/agent-linux-amd64 ./cmd/agent
cp /tmp/agent-linux-amd64 deploy/install.sh <面板>/public/agent/v1/
```
**✅ 对了吗**:`curl -sI https://app.91app.shop/agent/v1/install.sh` 返 200。
`[!]` 这目录在 `.gitignore` 里(**别把 24MB 二进制提交进仓库**)→ 换机器部署面板后要**重新发布一次**。

---

## 1. 一键部署(推荐)

🎯 **这步在干嘛**:后台点一下,面板 SSH 进目标机、让它自己下载安装。

后台 → 节点管理 → 该节点行 **「🚀部署」**。弹窗填:
- **SSH 主机 / 端口 / 用户** + **私钥正文或密码**;
- **二进制根地址** `https://app.91app.shop/agent/v1`;
- **落地节点会多出几项**(因为落地要连 91vpn 拉配置):**91vpn 面板地址 / node id / 协议 / 91vpn 节点 secret**——从该节点编辑页复制;还有 **accept_proxy** 开关(直连落地**取消勾**,挂中转才勾)。
- **中转节点则不用填身份**——面板自动把编译好的转发规则一起推过去。

点开始,日志实时回显,跑到 `✅ 部署完成,节点已就绪` 即成。

`[!]` 凭据只走 **stdin**,不进命令行、不落盘,进程结束即消失。面板只记"装到哪台、结果、日志",**绝不存 SSH 私钥/密码**。
`[!]` `host_key` 是首次连上的 SSH 指纹(TOFU),下次同台指纹变了会告警(换机/中间人信号)——所以部署记录**只清日志正文、不删行**。

---

## 2. 手工安装(备选)

🎯 **这步在干嘛**:不想用后台按钮时,直接在节点机上跑 `install.sh`。

先把 secret 写进文件、拉下 install.sh:
```bash
umask 077 && printf '%s' '<粘贴 secret>' > /root/node.secret     # 别用 --api-key,那会进 ps
curl -fsSL https://app.91app.shop/agent/v1/install.sh -o /tmp/install.sh
```

**落地节点**(面板模式):
```bash
bash /tmp/install.sh \
  --base-url https://app.91app.shop/agent/v1 \
  --panel sspanel-uim \
  --api-url https://app.91app.shop \
  --node-id 59 \
  --api-key-file /root/node.secret \
  --server-type vless \
  [--accept-proxy]              # 只收中转流量的落地才加,见 [05]
```
**中转节点**(纯中转,不连面板):
```bash
bash /tmp/install.sh --base-url https://app.91app.shop/agent/v1 \
  --relay-mode --forward-file /tmp/forward.json
```

`[!!]` **`--base-url` 不能省**——脚本靠它按本机架构(amd64/arm64)拼出下载地址;少了直接报 `缺 --binary / --binary-url / --base-url`。
`[!]` 重装同一台加 `--force-conf`,否则脚本**保留已有配置不动**(升级不该顺手改掉你调过的配置)。

`install.sh` 会:装二进制到 `/usr/local/bin/agent`、写 `/etc/agent/agent.conf`(0600)、装 `agent.service`(`Restart=always`+开机自启)、按内存设 `GOMEMLIMIT`、等 `/ready` 通过才算成功。
**✅ 对了吗**:最后 `✅ 安装完成，节点已就绪`。

---

## 3. `agent.conf` 配置项(参考)

键值对,每行一个,与 soga 配置同构(便于迁移)。

**必填**:`type`(面板类型 sspanel-uim/v2board/soga-v1…)、`server_type`(vmess/vless/trojan/shadowsocks)、`node_id`、`api`(webapi/db);`api=webapi` 再要 `webapi_url`/`webapi_key`;`api=db` 再要 `db_host/db_port/db_name/db_user/db_password`。

**常用**:

| 键 | 默认 | 说明 |
|---|---|---|
| `check_interval` | 60 | 拉配置与用户的间隔(秒) |
| `submit_interval` | 60 | 上报流量与状态的间隔(秒) |
| `accept_proxy_protocol` | false | 入站收 PROXY 头(见 [05](05-relay.md)) |
| `user_speed_limit` / `speed_limit_unit` | 0 | 限速 |
| `forward_file` | | 中转模式的规则文件 |
| `relay_mode` | false | 纯中转(不连面板拉用户) |

`[!]` 观测端点监听地址**不在配置文件里**——是 `install.sh` 的 `--api-addr`(默认 `127.0.0.1:9090`),最终落到 systemd 的 `-api` 启动参数。

**证书**:`cert_mode`(none/file/http/dns)、`cert_domain`/`cert_file`/`key_file`、`cert_mode=dns` 时 `dns_provider` + 该 provider 的环境变量。
**规则与地理库**:`block_list_file` · `white_list_file` · `routes_file` · `dns_file` · `geosite_file` · `geoip_file`。

`[!]` 完整清单以 `agent/internal/config/config.go` 为准。遇到**未实现但 soga 支持的键**,agent **明确报错而不是静默忽略**(静默忽略会让人以为配置生效了)。

---

## 4. 验收

```bash
systemctl is-enabled agent && systemctl is-active agent   # enabled / active
curl -s 127.0.0.1:9090/health                             # 进程活着
curl -s 127.0.0.1:9090/ready                              # {"ready":true}=可服务
curl -s 127.0.0.1:9090/metrics | head                     # 指标
ss -tlnp | grep agent                                     # 监听端口
```
`[!!]` `/health`(进程活着)≠ `/ready`(能服务):REALITY 节点 dest 挂掉时,端口照听、`/health` 照样 200,但没人能握手——那时 `/ready` 失败。**判断能不能用看 `/ready`。**

---

## 5. 升级

重跑一次一键部署即可(install.sh **幂等**,默认**保留已有配置**,要覆盖用 `--force-conf`)。

---

## 接下来

- 上 REALITY → [06](06-reality.md) · 加中转 → [05](05-relay.md) · 出问题 → [09](09-ops.md)
