# 把 91vpn 面板挂上固定域名（Cloudflare named tunnel）

面板容器跑在 `8088`。这份文档把它挂到 `91app.shop` 下的一个固定主机名上。

`[!!]` 为什么必须换掉 quick tunnel（`trycloudflare.com`）：它的主机名是每次
启动**随机分配**的，进程一重启就换一个。而**每个节点的 `agent.conf` 里
`webapi_url` 写死的就是这个域名**——进程一挂，所有节点同时失联。
现在这个 quick tunnel 是个裸进程（不是 systemd），已经跑了 3 天，
一次意外重启就等于全网断联。

`[!!]` 与中转面板最大的不同：**这个面板同时服务用户、节点和管理员**。
中转面板是纯后台，可以整站套 Cloudflare Access；这里不行 —— 226 个真实
用户要访问 `/login`、`/user/*` 拿订阅，节点要打 `/mod_mu/*`。整站套 Access
会把他们全挡在门外。

## [decided] 方案 A：两个主机名，一条隧道

| 主机名 | Access | 给谁用 |
|---|---|---|
| `admin.91app.shop` | ✅ 整站保护（只有你的邮箱） | 管理后台 |
| `app.91app.shop` | ❌ 不套 | 用户网页、客户端 API、节点心跳 |

两条 Public Hostname 挂在**同一条隧道**上，都指向 `127.0.0.1:8088`。

选它而不是"单主机名 + 一堆 Bypass 路径"，是因为后者要逐条列出
`mod_mu` `sub` `api` `login` `register` `user` `css` `js`… 漏一条用户就报
"打不开"，而且以后每加一个用户页都要记得回来补。两个主机名的边界是清楚的。

`[!!]` 方案 A 有个必须堵的洞：**两个主机名指向同一个应用**，所以
`app.91app.shop/admin/*` 本来是可达的 —— 那条路径上没有 Access，
后台等于从侧门敞着。已在应用侧补了一道
（`Middleware\AdminHost` + `ADMIN_HOST` 环境变量，走错主机名一律 404
而不是 403/302 —— 后两者等于告诉探测者"这里确实有后台"）。

不靠 Cloudflare 的 WAF 规则来挡，是因为那是一条**改错就静默失效**的外部
配置。本轮已经踩过一次同类的：追加到 INPUT 链尾的 iptables DROP 形同虚设，
而部署日志照样打印"其余 DROP"。

---

## 阶段 0：先确认一件事

**#59（191.96.31.236）是这次迁移里唯一从本机 SSH 不上去的节点**，而它承载
真实用户。换域名后它的 `webapi_url` 必须跟着改，否则旧隧道一停它就失联。
开工前先确认你用什么方式登录它（另一把密钥 / 机房控制台 / 面板商)。

---

## 阶段 1：Cloudflare 后台（只有你能做）

### 1.1 清理旧隧道的残留

旧隧道虽然删了，**Access 应用和 DNS 记录还在**（`relay.91app.shop` 目前仍会
302 跳到 `mute-hill-84ea.cloudflareaccess.com`）。

- Zero Trust → Access → Applications → 删掉 **`relaypanel-agent-api`** 和
  **`relaypanel-admin`** 这两个（都绑在 `relay.91app.shop` 上）
- DNS → Records → 删掉 `relay` 那条记录

`[!]` 那两个应用是中转面板的"Bypass 节点端点 + 保护其余"结构。方案 A 不
沿用它：91vpn 的公开面比中转面板大得多（用户网页 + 客户端 API + 节点），
靠列 Bypass 路径迟早漏一条。这里改成用主机名切分。

### 1.2 建隧道

Zero Trust → Networks → Tunnels → **Create a tunnel** → **Cloudflared**

- 名字：`91vpn`
- 建完给出的安装命令里，`eyJ` 开头的长串就是**连接器 token**（先别贴聊天里）

### 1.3 配两条 Public Hostname

同一条隧道下加两条（Tunnel → Public Hostname → Add a public hostname）：

| Subdomain | Domain | Service Type | URL |
|---|---|---|---|
| `admin` | `91app.shop` | **HTTP** | `127.0.0.1:8088` |
| `app` | `91app.shop` | **HTTP** | `127.0.0.1:8088` |

`[!]` Service 选 **HTTP** 不是 HTTPS：容器里跑的是明文 nginx，TLS 由
Cloudflare 到浏览器那一段负责。选 HTTPS 会让 cloudflared 用 TLS 去连一个
没有 TLS 的端口。

DNS 记录 Cloudflare 会自动建，不用手动加。

### 1.4 Access 应用（只给 admin 那个主机名）

Zero Trust → Access → Applications → Add an application → **Self-hosted**

| 字段 | 值 |
|---|---|
| Application name | `91vpn-admin` |
| Application domain | `admin.91app.shop` |
| Path | **留空**（整站） |

策略：Action = **Allow**，Include = **Emails** → 你的邮箱。
登录方式用 Cloudflare 自带的 **One-time PIN**，不需要额外 IdP。

`[!!]` **不要**给 `app.91app.shop` 建任何 Access 应用，也不要建
`*.91app.shop` 的通配应用 —— 那会把用户和节点一起挡掉，而症状很迷惑：
你从浏览器（已通过 Access）看一切正常，只有用户和节点那边"面板挂了"。

`[!]` 旧的 `relaypanel-agent-api` / `relaypanel-admin` 两个应用直接删掉：
它们绑的 `relay.91app.shop` 隧道已经不存在了。

### 1.5 放行机器人检测

`[!!]` agent 发的是**伪装的 Chrome UA**（复刻 soga 的行为，见
`sogacore/agent/internal/panel/httpclient.go`），底下却是 Go 的 TLS 栈。
UA 自称浏览器、TLS 指纹不是——这正是 Cloudflare 机器人检测最爱抓的特征。
被拦的话节点收到 403，而浏览器访问一切正常。

二选一：

- Security → Bots → **Bot Fight Mode 关掉**；或
- Security → WAF → Custom rules → 新建一条，表达式
  `starts_with(http.request.uri.path, "/mod_mu/") or starts_with(http.request.uri.path, "/sub/") or starts_with(http.request.uri.path, "/api/")`
  动作选 **Skip**，勾上 Managed Rules / Bot 检测 / Rate limiting

### 1.6 顺手检查

- **Cache Rules**：确认没有规则命中 `/sub/*`（订阅必须每次取新的）
- **Speed → Optimization**：Rocket Loader 关掉（后台一键部署页靠 JS 轮询日志）

---

## 阶段 2：机器上装隧道

token 拿到后，在 Claude Code 里用 `!` 开头执行（这样只经过你的 shell）：

```
! umask 077 && printf '%s' '<粘贴 token>' > ~/cf-91vpn-token && echo 已写入
! sudo bash /home/dev/web/91vpn/deploy/install-tunnel.sh ~/cf-91vpn-token
! shred -u ~/cf-91vpn-token
```

脚本做的事：建专用系统用户 `cfpanel`、token 存成 `root:cfpanel 0640`、
用 `--token-file` 写 systemd 单元、开机自启、**以 cfpanel 身份实测一次能否
读到 token**，最后清理旧的 `cloudflared-relaypanel` 服务与作废 token。

`[!]` 用 `--token-file` 而不是 `--token`：后者会让 token 出现在进程命令行里，
本机任何用户 `ps` 一下就拿到了这条隧道的控制权。

验证：

```
systemctl status cloudflared-91vpn        # active
curl -sI https://app.91app.shop/login     # 200（用户面）
curl -sI https://admin.91app.shop/        # 302 → Cloudflare Access 登录页
```

---

## 阶段 3：两个地址并存，先验证

`[!!]` **这一步不要停 quick tunnel**。新旧地址同时可用，验证完再撤。

```
curl -sI https://app.91app.shop/login                  # 用户面 200
curl -s  https://app.91app.shop/mod_mu/nodes/60/info   # 节点接口(带 key 才有数据,这里看是否被 Access/WAF 拦)
curl -sI https://admin.91app.shop/                     # 302 → Access 登录页,说明后台确实被保护
```

要看到的是面板自己的响应，**不是** Access 登录页的 302、也不是 403。

---

## 阶段 4：切 APP_URL 与 ADMIN_HOST

```
sed -i 's|^APP_URL=.*|APP_URL=https://app.91app.shop|' /home/dev/web/91vpn/.env
grep -q '^ADMIN_HOST=' /home/dev/web/91vpn/.env \
  || echo 'ADMIN_HOST=admin.91app.shop' >> /home/dev/web/91vpn/.env
docker exec 91vpn-app-1 php artisan config:clear
```

`[!!]` `APP_URL` 填的是**用户面那个主机名**（`app.`），不是 admin：
用户订阅链接 `url('/sub/'.$token)` 和节点的 `webapi_url` 都由它派生，
填成 admin 的话每个用户和节点都会撞上 Access 登录页。

`ADMIN_HOST` 是应用侧那道补挡（见上面方案 A 的说明）。配上之后
从 `app.91app.shop/admin` 进后台会得到 404。

验证：

```
curl -sI https://app.91app.shop/admin/nodes | head -1      # 404
curl -sI https://app.91app.shop/login | head -1            # 200
```

## 阶段 5：逐台更新节点（顺序：先测试机，后生产）

每台都是同一个动作：改 `webapi_url`，重启 agent，确认心跳恢复。

```
sed -i 's|^webapi_url=.*|webapi_url=https://app.91app.shop|' /etc/agent/agent.conf
systemctl restart agent
journalctl -u agent -n 20 --no-pager | grep -iE "panel|sync|error"
```

| 节点 | 位置 | 配置路径 | 谁来做 |
|---|---|---|---|
| #60 影子节点 | 179.253.249.78 | `/etc/agent/agent.conf`（systemd） | 本机可 SSH |
| #93 中转 | 179.253.249.78 | 手工进程的 conf（非 systemd） | 本机可 SSH |
| **#59 生产** | 191.96.31.236 | 待确认 | **本机 SSH 不上,需你操作** |

改完在面板节点页确认三台心跳都在 60 秒内。

---

## 阶段 6：收尾

```
# 1. 容器绑定收紧:现在是 0.0.0.0:8088,同机其它容器/用户能直连,绕过 Cloudflare
sed -i 's|"8088:80"|"127.0.0.1:8088:80"|' docker-compose.yml && docker compose up -d nginx

# 2. 三台节点心跳都正常【之后】,再停掉 quick tunnel 裸进程
pkill -f "cloudflared tunnel --url http://localhost:8088"
```

`[!]` 顺序不能反。绑定收紧要在隧道跑起来之后（cloudflared 连的是
`127.0.0.1:8088`，不受影响）；quick tunnel 要在最后一台节点确认之后。

用户订阅链接换了域名，旧链接会失效——需要通知一次。不过 quick tunnel 的
域名本来就随时会变，等于现在所有人的订阅都挂在一根随时断的线上。

---

## 回滚

阶段 4 之前：什么都不用回滚，quick tunnel 一直在跑。

阶段 4 之后节点连不上新地址：把 `.env` 的 `APP_URL` 和节点的 `webapi_url`
改回 quick tunnel 域名即可——**前提是 quick tunnel 还活着**，这就是它要留到
最后才停的原因。
