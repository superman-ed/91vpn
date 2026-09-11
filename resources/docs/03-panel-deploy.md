# 03 · 面板部署 —— 挂域名 + 锁死后台

> **读法**:零假设。每节先说**这步在干嘛**,命令都给 → ✅对了吗。纯文字。
> 全程用真实数值:用户面 `app.91app.shop`、后台 `summer.91app.shop`(**后台子域名用了冷僻名**,原因见下)。
> 本章界面路径照一次真实迁移记录,Cloudflare 界面改版勤,名字对不上按"它是干嘛的"去找。

---

## 开始前(不满足先回去)

- [ ] 已按 [02](02-quickstart.md) 用 SSH 隧道**跑通面板**(能登录后台、能建节点)。
- [ ] 有一个域名(本章用 `91app.shop`)。
- [ ] 准备好**两个子域名**:一个给用户面(`app`)、一个给后台。
  - `[!!]` **后台子域名别用 `admin`/`panel`/`backend` 这种能猜的**。我们用的是 `summer` 这种普通词——因为域名一旦签证书就会进公开的证书透明日志,别人能列出你所有子域名;取个冷僻名多挡一层扫描(真正挡人的是后面的 Access,冷僻名是白送的额外一层)。

---

## 1. 面板容器长什么样

🎯 **这步在干嘛**:先认清 6 个服务和 nginx 的两个入口,后面配隧道才不懵。

`docker-compose.yml` 里 6 个服务:

| 服务 | 作用 |
|---|---|
| `app` | PHP-FPM,跑 Laravel |
| `web` | nginx,**两个 server 块**(见下) |
| `scheduler` | `artisan schedule:work`,定时任务 |
| `cloudflared` | 隧道连接器,读 `.env` 的 `CLOUDFLARE_TUNNEL_TOKEN` |
| `db` / `redis` | 数据与缓存 |

nginx 两个 server 块,**都只绑回环**(`127.0.0.1:8088` / `127.0.0.1:18088`),对外一律经隧道:

```
:80   → 客户端 API / mod_mu / 公开页    —— /admin 一律 404
:8080 → 管理后台专用入口                —— 只放 /login /logout /admin
```

`[!!]` 曾经 `:80` 绑 `0.0.0.0`,实测**IPv6 上对外可直连**(`http://[机器IPv6]:8088/login` 就绕过了 Cloudflare)。改成只绑回环后才真正关上。

---

## 2. Cloudflare Tunnel(核心)

🎯 **这步在干嘛**:面板只绑回环、外面进不来;隧道在这台机和 Cloudflare 之间建一条**出站**长连,用户访问域名时由 Cloudflare 把请求从隧道送进来。**好处**:不开任何入站端口、源站 IP 不暴露、证书 Cloudflare 管。

### 2.0 前提:域名 NS 托管到 Cloudflare

1. Cloudflare 主面板 → **Add a site** → 填 `91app.shop` → 选 **Free**。
2. 它给你两个 NS(形如 `xxx.ns.cloudflare.com`)。
3. 到**域名注册商**把 NS 改成这两个。
4. 等生效(几分钟~几小时),验证:
   ```bash
   dig +short NS 91app.shop      # 返回 Cloudflare 那两个 = 生效
   ```
   ✅ 没生效之前后面全会失败,**先把这步坐实**。

### 2.1 建隧道拿 token

**Zero Trust 面板**(`one.dash.cloudflare.com`,和主面板是两个地方)→ 左侧 **Networks → Tunnels → Create a tunnel** → 选 **Cloudflared**。
- 名字随便,比如 `panel`;
- 建完给你一条安装命令,里面 **`eyJ` 开头的一长串**就是**连接器 token**。

`[!!]` 这串 token = 隧道控制权,**别贴聊天/工单/截图**,直接写进 `.env`。

### 2.2 token 交给面板

```bash
# 面板机上
nano .env      # CLOUDFLARE_TUNNEL_TOKEN= 后面粘上
docker compose up -d cloudflared
docker compose logs cloudflared | tail -20
```
**✅ 对了吗**:看到 `Registered tunnel connection`(通常 4 条),容器状态 `running` 不再 `restarting`;Zero Trust 隧道列表那条显示 **HEALTHY**。

### 2.3 找到"发布主机名"页

点进你那条隧道,标签页(新版名)里点 **Published application routes**(就是老版的 **Public Hostname**,改名了)。
`[!]` 另外的 `CIDR routes`/`Hostname routes` 是私有网络(WARP)用的,和我们无关,别点错。
`[!]` **Live logs** 标签记一下:切换后有问题,在那看请求有没有打进来、返回什么码,比翻日志快。

### 2.4 加**两条**路由(挂在同一条隧道上)

点 **Add**,加两条:

| Subdomain | Domain | Type | URL |
|---|---|---|---|
| `summer`(你的冷僻后台名) | `91app.shop` | **HTTP** | `http://web:8080` |
| `app` | `91app.shop` | **HTTP** | `http://web:80` |

Path 栏留空。**三个最容易错的地方**:

`[!!]` **Type 选 HTTP 不是 HTTPS**:容器里是明文 nginx,TLS 由 Cloudflare 到浏览器那段负责。选 HTTPS → cloudflared 用 TLS 连一个没 TLS 的口 → **502**。
`[!!]` **URL 填服务名 `web:80`/`web:8080`,不是 `127.0.0.1:8088`**:连接器在 compose 网里,`127.0.0.1` 指它自己那个容器,那儿啥都没有。
`[!!]` **两条指向不同端口别搞反**:`:8080` 是后台入口、`:80` 是用户面(它上面 `/admin` 一律 404)。指反了症状是"后台过了 Access 还是 404"或"用户打不开 `/user/*`"。
`[!]` **别点第二次 Create a tunnel**:两条路由挂**同一条隧道**,第二条隧道 = 第二个 token,纯多余。

DNS 记录 Cloudflare 自动建。验证:
```bash
curl -sI https://app.91app.shop/login      # 200
curl -sI https://summer.91app.shop/         # 302(配完下一步 Access 之后)
```

### 2.5 `[!!]` Access:只保护后台那个主机名

🎯 **这步在干嘛**:给后台域名前面加一道"身份门",别人就算知道 `summer.91app.shop` 也先撞 Cloudflare 登录、看不到你后台登录页。

Zero Trust → **Access → Applications → Add an application → Self-hosted**:

| 字段 | 填 |
|---|---|
| Application name | `panel-admin` |
| Application domain | `summer.91app.shop` |
| Path | 留空(保护整站) |

策略:**Action = Allow**,**Include = Emails** → 填管理员邮箱(多个管理员填多个)。登录方式用 Cloudflare 自带 **One-time PIN** 即可。

**`[!!]` 绝对不要做的事**:**别给 `app.91app.shop` 建任何 Access 应用**,也别建 `*.91app.shop` 通配应用。这个面板同时服务三类调用方:

| 路径 | 谁在调 | 被 Access 挡住的后果 |
|---|---|---|
| `/admin/*` | 管理员 | 正确,就该挡 |
| `/mod_mu/*` | 节点心跳 | **所有节点失联** |
| `/sub/*` `/api/*` | 用户与客户端 | **所有用户拉不到配置** |

`[!!]` 症状极迷惑:**你从浏览器看一切正常**(你已过 Access),只有节点和用户那边"面板挂了"。
`[!]` 以前建过通配 Access 应用的话,去 Applications 列表确认它不会顺手罩住新主机名。

### 2.6 `[!!]` 放行机器人检测

🎯 **这步在干嘛**:agent 的 UA 伪装成 Chrome、底下却是 Go 的 TLS 栈——正是 Cloudflare 机器人检测爱抓的。被拦时节点收 **403**,而你浏览器一切正常。

二选一:
- **简单**:主面板 → **Security → Bots** → 关 **Bot Fight Mode**。
- **精细**:主面板 → **Security → WAF → Custom rules** → 新建,表达式:
  ```
  starts_with(http.request.uri.path, "/mod_mu/")
  or starts_with(http.request.uri.path, "/sub/")
  or starts_with(http.request.uri.path, "/api/")
  ```
  Action 选 **Skip**,勾上 Managed Rules / Bot 检测 / Rate limiting。

### 2.7 顺手检查(容易忽略的坑)

| 在哪 | 看什么 |
|---|---|
| 主面板 → Caching → Cache Rules | 别有规则命中 `/sub/*`——订阅必须每次取新的 |
| 主面板 → Speed → Optimization | **Rocket Loader 关掉**——后台一键部署页靠 JS 轮询日志,它改写脚本加载顺序可能弄坏 |
| 主面板 → DNS → Records | 删废弃旧记录(删了隧道但 DNS 还在 → 访问返 **530**) |

### 2.8 一次性验证清单

```bash
dig +short NS 91app.shop                                # Cloudflare 那两个 NS
docker compose ps cloudflared                           # running
docker logs <项目>-cloudflared-1 | grep -i registered   # 有 Registered tunnel connection
curl -sI https://app.91app.shop/login                   # 200
curl -sI https://app.91app.shop/admin                   # 404(公网口不放后台)
curl -sI https://summer.91app.shop/                     # 302 → cloudflareaccess.com
curl -s "https://app.91app.shop/mod_mu/users?node_id=1&key=错的"   # 401,不是 403
```
`[!]` 最后一条区分**面板拒绝**(401,密钥不对,请求打到了面板)和**Cloudflare 拒绝**(403,被机器人检测拦在外)。

### 2.9 换/重建隧道时

删隧道**不会**自动删它的 DNS 记录和 Access 应用,会变悬空:
- 悬空 DNS → 访问返 **530**;
- 悬空 Access → 仍把你重定向到 `xxx.cloudflareaccess.com`。
重建时一并清理。

---

## 3. `.env` 三个关键项

| 键 | 填什么 |
|---|---|
| `APP_URL` | **用户面**主机名 `https://app.91app.shop`——订阅链接与节点 `webapi_url` 都由它派生 |
| `ADMIN_HOST` | 后台主机名 `summer.91app.shop`——配了之后 `/admin/*` 只能从它进,其余一律 **404** |
| `CLOUDFLARE_TUNNEL_TOKEN` | 隧道连接器 token |

`[!!]` `APP_URL` 千万别填成后台主机名——那样每个用户和节点都会撞上 Access 登录页。
`[!]` `ADMIN_HOST` 是**应用侧的补挡**(两个主机名指同一应用,`app/admin/*` 本来可达)。用它而不靠 Cloudflare WAF 规则挡,是因为 WAF 那种是"改错就静默失效"的外部配置,应用侧兜底更硬。

---

## 4. 定时任务(scheduler 容器自动跑)

| 命令 | 频率 | 作用 |
|---|---|---|
| `alive-ips:prune` | 5 分钟 | 清过期在线 IP |
| `nodes:mark-offline` | 1 分钟 | 心跳超时的节点标离线 |
| `traffic:reset-daily` / `-monthly` | 每天 | 流量周期重置 |
| `logs:prune` | 每天 04:00 | 各类流水表保留期清理(见下) |

**保留策略**(`logs:prune`):登录日志 90 天、崩溃 180、各类流量 365、操作日志 180(`[!!]` **登录失败留 2 倍**——唯一能看出"有人长期试探"的信号)、订阅拉取 90、站内通知 90(`[!!]` 只清已读)、邮件 180、设备 180(按 `last_seen`)、**部署记录只清日志正文不删行**(`[!!]` `host_key` 是 SSH 指纹 TOFU 链,删行就比不出"机器被换了")。

---

## 5. 备份

至少备份**数据库**与 **`.env`**。`.env` 里有隧道 token 和应用密钥,**丢了等于隧道和会话都要重建**。(详见 [25 备份与恢复] 章——待补。)

---

## 接下来

- 装节点 → [04 · 节点部署](04-node-deploy.md)
- 上 REALITY → [06 · REALITY 上线](06-reality.md)
- 出问题 → [09 · 运维手册](09-ops.md)
