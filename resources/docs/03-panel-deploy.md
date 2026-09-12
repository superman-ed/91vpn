# 03 · 面板部署

## 1. 容器

`docker-compose.yml` 里有六个服务：

| 服务 | 作用 |
|---|---|
| `app` | PHP-FPM，跑 Laravel |
| `web` | nginx，两个 server 块（见下） |
| `scheduler` | `artisan schedule:work`，定时任务 |
| `cloudflared` | 隧道连接器，读 `.env` 的 `CLOUDFLARE_TUNNEL_TOKEN` |
| `db` / `redis` | 数据与缓存 |

### `[!!]` nginx 的两个 server 块

```
:80   → 客户端 API / mod_mu / 公开页    —— /admin 一律 404
:8080 → 管理后台专用入口                —— 只放 /login /logout /admin
```

两个端口**都只绑回环**（`127.0.0.1:8088` / `127.0.0.1:18088`）。
对外一律经隧道，源站 IP 不暴露。

`[!!]` 曾经 `8088` 绑的是 `0.0.0.0`，实测**在 IPv6 上对外可连** ——
直接 `http://[<机器IPv6>]:8088/login` 就能打开面板，绕过 Cloudflare。
改绑定之后才真正关上。

## 2. Cloudflare Tunnel

`[!]` 本节的界面路径与每条验证命令，都是照着一次**真实迁移**记下来的
（2026-09），并在写完后逐条重跑确认过。Cloudflare 的界面改版较勤，
名字对不上时按"它是干什么的"去找，不要死抠字面。

面板容器只绑回环，外面进不来。Cloudflare Tunnel 在这台机器和 Cloudflare
之间建一条出站长连接，用户访问域名时由 Cloudflare 把请求从这条隧道送进来。

**好处**：不用开任何入站端口、源站 IP 不暴露、证书由 Cloudflare 管。

### 2.0 前提：域名的 NS 要托管在 Cloudflare

绕不开的一步。

1. Cloudflare 主面板 → **Add a site** → 填你的域名 → 选 **Free**
2. Cloudflare 给你两个 NS 地址（形如 `xxx.ns.cloudflare.com`）
3. 到**域名注册商**（买域名的地方）把 NS 改成这两个
4. 等生效，几分钟到几小时。验证：

```bash
dig +short NS 你的域名
# 返回 Cloudflare 给的那两个即为生效
```

`[!]` 没生效之前，后面每一步都会以各种形式失败，先把这步坐实。

### 2.1 建隧道

**Zero Trust 面板**（`one.dash.cloudflare.com`，和主面板是两个地方）
→ 左侧 **Networks** → **Tunnels** → **Create a tunnel** → 选 **Cloudflared**。

- 名字随便，比如 `panel`
- 建完会给你一条安装命令，里面 **`eyJ` 开头的那串很长的**就是**连接器 token**

`[!!]` 那串 token 等于这条隧道的控制权。**别贴进聊天、工单、截图。**
下一步直接写进 `.env`。

### 2.2 把 token 交给面板

```bash
# 在面板机上
nano .env      # 找到 CLOUDFLARE_TUNNEL_TOKEN= 这一行，粘在等号后面
docker compose up -d cloudflared
docker compose logs cloudflared | tail -20
```

**应该看到**：`Registered tunnel connection` 之类的行，而且容器状态是
`running` 不再是 `restarting`。

Zero Trust 的隧道列表里，那条隧道应当显示 **HEALTHY**。

### 2.3 `[!!]` 找到"发布主机名"那个页面

点进你那条隧道，会看到几个标签页。新版界面里它们叫：

```
Overview | CIDR routes | Hostname routes | Published application routes | Live logs
```

要点的是 **Published application routes** —— 它就是老版本里的
**Public Hostname**，Cloudflare 改了名。

`[!]` 另外两个 routes 是**私有网络**用的（给装了 WARP 客户端的设备访问内网），
和我们要做的事无关。名字很像，很容易点错。

`[!]` **Live logs** 这个标签页记一下：切换完之后如果节点或用户有问题，
在那里能直接看到请求有没有打进来、返回什么码，比翻日志快。

### 2.4 加两条路由

点 **Add**（或 **Create**），加**两条**：

| Subdomain | Domain | Type | URL |
|---|---|---|---|
| `admin` | 你的域名 | **HTTP** | `http://web:8080` |
| `app` | 你的域名 | **HTTP** | `http://web:80` |

Path 那栏填 `*` 或留空（两者等价）。

**三个容易错的地方：**

`[!!]` **Type 选 HTTP 不是 HTTPS。** 容器里跑的是明文 nginx，
TLS 由 Cloudflare 到浏览器那一段负责。选 HTTPS 会让 cloudflared 用 TLS
去连一个没有 TLS 的端口，结果是 502。

`[!!]` **URL 填 compose 服务名，不是 `127.0.0.1:8088`。** 连接器跑在
compose 网络里，`127.0.0.1` 对它来说是它自己那个容器 —— 那里什么都没有。
用 `web:80` / `web:8080` 它才找得到。

`[!!]` **两条路由指向不同端口。** nginx 的两个 server 块分工不同：
`:8080` 是后台专用入口，`:80` 是用户面（`/admin` 在这个口上一律 404）。
指反了的症状是：后台过了 Access 也只看到 404，或者用户打不开 `/user/*`。

`[!]` **别点第二次 "Create a tunnel"。** 两条路由挂在**同一条隧道**上，
第二条隧道意味着第二个 token、第二个连接器，纯属多余。

DNS 记录 Cloudflare 会自动建，不用手动加。

验证：

```bash
curl -sI https://app.你的域名/login      # 200
curl -sI https://admin.你的域名/         # 302（下一步配了 Access 之后）
```

### 2.5 `[!!]` Access：只保护后台那个主机名

Zero Trust → **Access** → **Applications** → **Add an application** → **Self-hosted**

| 字段 | 填什么 |
|---|---|
| Application name | `panel-admin` |
| Application domain | `admin.你的域名` |
| Path | 留空（保护整站） |

策略：**Action = Allow**，**Include = Emails** → 填管理员邮箱（**多个管理员就填多个**）。
登录方式用 Cloudflare 自带的 **One-time PIN** 即可，不需要额外身份提供商。

**绝对不要做的事：**

`[!!]` **不要给 `app.你的域名` 建任何 Access 应用**，也不要建
`*.你的域名` 这种通配应用。这个面板同时服务三类调用方：

| 路径 | 谁在调 | 被 Access 挡住的后果 |
|---|---|---|
| `/admin/*` | 管理员 | 正确，就该挡 |
| `/mod_mu/*` | 节点心跳 | **所有节点失联** |
| `/sub/*` `/api/*` | 用户与客户端 | **所有用户拉不到配置** |

症状非常迷惑：**你从浏览器看一切正常**（因为你已经通过了 Access），
只有节点和用户那边"面板挂了"。

`[!]` 如果账号里以前建过通配的 Access 应用，去 Applications 列表里确认一下
它不会顺手罩住新主机名。

### 2.6 `[!!]` 放行机器人检测

agent 发的是**伪装成浏览器的 User-Agent**（复刻 soga 的行为），
底下却是 Go 的 TLS 栈。UA 说自己是 Chrome、TLS 指纹却不是 ——
这正是 Cloudflare 机器人检测最爱抓的特征。

被拦的话节点收到 **403**，而你从浏览器访问一切正常。

二选一：

**简单做法**：主面板 → **Security** → **Bots** → 关掉 **Bot Fight Mode**。

**精细做法**：主面板 → **Security** → **WAF** → **Custom rules** → 新建一条：

- 表达式（用 Edit expression 粘进去）：

```
starts_with(http.request.uri.path, "/mod_mu/")
or starts_with(http.request.uri.path, "/sub/")
or starts_with(http.request.uri.path, "/api/")
```

- Action 选 **Skip**，勾上 Managed Rules / Bot 检测 / Rate limiting

### 2.7 顺手检查

| 在哪 | 看什么 |
|---|---|
| 主面板 → Caching → Cache Rules | 确认没有规则命中 `/sub/*` —— 订阅必须每次取新的 |
| 主面板 → Speed → Optimization | **Rocket Loader 关掉** —— 后台的一键部署页靠 JS 轮询日志，它改写脚本加载顺序有可能弄坏 |
| 主面板 → DNS → Records | 有废弃的旧记录就删掉（删了隧道但 DNS 还在的话，访问会返回 **530**） |

### 2.8 每一步的验证命令（汇总）

```bash
dig +short NS 你的域名                          # 返回 Cloudflare 的两个 NS
docker compose ps cloudflared                   # running（不是 restarting）
docker logs <项目>-cloudflared-1 | grep -i registered   # 有 Registered tunnel connection
curl -sI https://app.你的域名/login              # 200
curl -sI https://app.你的域名/admin              # 404（公网口不放后台）
curl -sI https://admin.你的域名/                 # 302 → cloudflareaccess.com
curl -s https://app.你的域名/mod_mu/users?node_id=1&key=错的   # 401,不是 403
```

`[!]` 最后一条是用来区分**面板拒绝**与**Cloudflare 拒绝**的：
401 说明请求打到了面板（只是密钥不对），403 多半是被机器人检测拦在外面了。

### 2.9 换/重建隧道时

删掉一条隧道**不会**自动删掉它的 DNS 记录和 Access 应用，那两样会变成悬空配置：

- 悬空 DNS 记录 → 访问返回 **530**
- 悬空 Access 应用 → 仍然会把你重定向到 `xxx.cloudflareaccess.com` 登录页

重建时记得一并清理。

## 3. `.env` 关键项

| 键 | 说明 |
|---|---|
| `APP_URL` | **用户面**主机名（`https://app.<域名>`）—— 订阅链接与节点 `webapi_url` 都由它派生 |
| `ADMIN_HOST` | 后台专用主机名。配了之后 `/admin/*` 只能从它进，其余一律 **404** |
| `CLOUDFLARE_TUNNEL_TOKEN` | 隧道连接器 token |

`[!!]` `APP_URL` 填成后台那个主机名的话，每个用户和节点都会撞上 Access 登录页。

`[!]` `ADMIN_HOST` 是**应用侧的补挡**：两个主机名指向同一个应用，
`app.<域名>/admin/*` 本来是可达的。不靠 Cloudflare 的 WAF 规则来挡，
是因为那是一条**改错就静默失效**的外部配置。

## 4. 定时任务

`scheduler` 容器跑 `schedule:work`。任务清单见 `routes/console.php`，其中与
数据生命周期有关的：

| 命令 | 频率 | 作用 |
|---|---|---|
| `alive-ips:prune` | 5 分钟 | 清过期在线 IP（用户侧 + 中转侧） |
| `nodes:mark-offline` | 1 分钟 | 心跳超时的节点标离线 |
| `traffic:reset-daily` / `-monthly` | 每天 | 流量周期重置 |
| `logs:prune` | 每天 04:00 | 各类流水表的保留期清理（见下） |

### 保留策略

`logs:prune` 覆盖的表与默认保留期：

| 表 | 保留 | 备注 |
|---|---|---|
| 登录日志 | 90 天 | |
| 崩溃日志 | 180 天 | |
| 日流量 / 节点日流量 / 规则流量 / 整机流量 | 365 天 | |
| 操作日志 | 180 天 | `[!!]` **登录失败保留 2 倍期限** —— 它是唯一能看出"有人在长期试探"的信号，而那种试探本来就是慢的 |
| 订阅拉取记录 | 90 天 | 客户端每次刷新订阅就是一行，涨得比日志还快 |
| 站内通知 | 90 天 | `[!!]` **只清已读的**，未读永不删 |
| 邮件记录 | 180 天 | |
| 设备记录 | 180 天 | `[!]` 按 `last_seen` 而不是 `created_at` |
| 部署记录 | **只清日志正文，不删行** | `[!!]` `host_key` 是 SSH 指纹的 TOFU 链，删了行就比不出"机器被换了" |

## 5. 备份

至少备份数据库与 `.env`。`.env` 里有隧道 token 与应用密钥，
**丢了等于隧道和会话都要重建**。
