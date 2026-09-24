# 部署：在一台新服务器上把 91VPN 跑起来

面向「**换台机器重建**」。读完能从空机器走到「面板可登录、节点可连接、备份在跑」。

`[!]` 这份文档只讲**怎么搭**。讲**为什么这么设计**的在 `docs/decisions/`，
讲**上线前还欠什么**的在 `docs/LAUNCH-CHECKLIST.md`。

---

## 0. 需要准备的

| | 说明 |
|---|---|
| 面板服务器 | 2C4G 起。它跑 MySQL / Redis / PHP / nginx，**不跑代理流量** |
| 节点服务器 | 按线路质量选，见 `docs/decisions/relay-boundaries.md` |
| 域名 | **至少 2 个可注册域**，见 §3 |
| Cloudflare | 面板走 Tunnel 出去；域名 DNS 也在这里管 |

`[!!]` **面板和节点必须分开。** `[D]` 2026-09-23 在面板机器上发现过一个跑了 15 天的
僵尸 agent，对公网开着 `*:34567` 代理端口 —— 而那台机器同时跑着数据库、Redis、
对象存储。见清单 L-11。

---

## 1. 面板

### 1.1 起服务

```bash
git clone <repo> 91vpn && cd 91vpn
cp .env.example .env
# 填 .env —— 关键项见 §1.2
docker compose up -d
docker compose exec app composer install --no-dev -o
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

`[!!]` **不要跑 `db:seed`。** `AdminSeeder` 会创建 `admin` / `password` ——
一个密码人尽皆知的管理员；`NodeSeeder` 会造一个指向 `127.0.0.1` 的测试节点。
新机器上手工建管理员：

```bash
docker compose exec app php artisan tinker
# >>> User::create(['username'=>'你的名字','email'=>'...','password'=>Hash::make('强密码'),'is_admin'=>true]);
```

`[!]` 确实要用种子（比如要那 5 个套餐模板）时，只跑指定的那个：
`php artisan db:seed --class=PlanSeeder`。

### 1.2 `.env` 里必须自己填的

```
APP_KEY                   artisan key:generate 生成
APP_URL                   面板对外域名
ADMIN_HOST                【后台专用域名】—— 见 §1.3，不填等于不设防
CLOUDFLARE_TUNNEL_TOKEN   Cloudflare Tunnel 的 token
DB_PASSWORD / REDIS_*     自己设
MAIL_*                    可留空（注册不走邮箱），但"忘记密码"那条路会断
```

`[!]` 其余项照抄 `.env.example` 即可。`APP_ENV=production` **要等支付走通之后再切**
——切了 mock 支付就没了，而那是目前唯一能测下单流程的方式（清单 L-01/L-02）。

### 1.3 `ADMIN_HOST` —— 后台隔离，两层

`[S]` `AdminHost` 中间件：请求主机名 ≠ `ADMIN_HOST` 时，`/admin*` 一律 **404**
（不是 403 —— 403 等于告诉探测者"这里确实有后台"）。

`[S]` nginx 另有一层：公网口（`127.0.0.1:8088`）把 `/admin` 一律 404；
后台口是 `127.0.0.1:18088`，**只监听回环**。

所以运维进后台的方式是：

```bash
ssh -L 18088:127.0.0.1:18088 <面板服务器>
# 然后本地浏览器开 http://localhost:18088/admin
```

`[!]` `ADMIN_HOST` 没配时中间件**放行** —— 它是加固项不是开关，
少一个环境变量不该让人打不开后台。但生产上必须配。

### 1.4 定时任务

`docker-compose.yml` 里的 `scheduler` 服务跑 `php artisan schedule:work`，
包含：流量日/月结、订单激活与过期、支付对账、节点离线标记、日志清理、备份记录等。

`[!!]` **没有 queue worker。** `.env` 里 `QUEUE_CONNECTION=redis`，但没有任何进程消费队列。
`[D]` 目前代码里没有任何 `dispatch()`，所以无害 —— 但**将来谁写了第一个，任务会进 Redis 然后永远不执行，且不报错**。
要用队列就得先加 worker 容器。

### 1.5 备份

```bash
tools/backup.sh        # 数据库 + .env，会校验 dump 内容
```

挂进 crontab。`[!]` 脚本头部记着写它的直接原因：**上一台机器上那条 cron 备的是
已经并入 91vpn、容器早就退出的 relaypanel，每天照跑、每天失败、没人看。**
一个只在失败时沉默的备份等于没有备份 —— 新机器上务必验证一次真能恢复。

---

## 2. 节点

### 2.1 装 agent

面板「节点管理 → 部署」会生成命令。二进制根地址已预填为本面板自己的
`<面板域名>/agent/v1`（面板就在发这些文件）。

手工装：

```bash
curl -fsSL https://<面板域名>/agent/v1/install.sh -o /tmp/i.sh
bash /tmp/i.sh --binary-url https://<面板域名>/agent/v1/agent-linux-amd64 \
  --panel sspanel-uim --node-id <N> --api-url https://<面板域名> --api-key <节点密钥>
```

`[!!]` **一台机器只能跑一个节点。** installer 是单实例的（`/etc/agent/agent.conf`、
`agent.service`、`/usr/local/bin/agent`、`-api 127.0.0.1:9090` 全写死）。
给已有节点的机器再装一个会被明确拒绝 —— 那道检查是 2026-09-23 补的，
在此之前它会**报成功而什么都不做**。

### 2.2 `agent.conf` 里两个面板改不动的值

```ini
server_type=vless        # 【协议】—— 面板改协议不会同步到这里
webapi_auth=header       # 密钥走 X-Node-Secret 头，不进访问日志
```

`[!!]` `server_type` 是 mod_mu 协议的形态（soga / XrayR 同样如此）：
**面板下发的 nodeInfo 里根本没有协议这一项**。在面板上把 vmess 改成 vless 而不改这里，
节点会静默地继续跑 vmess —— `[D]` 实测踩过，排查花了十四分钟。
面板现在会把节点上报的实际协议和配置并排显示，不一致时亮红（`90f4951`）。

`[!]` `webapi_auth=header` 不写则默认 `query`，密钥会逐条进访问日志
（`[D]` 取样 5 万行有 4.7 万行含明文密钥）。新装的机器直接配上。

### 2.3 REALITY 节点

```
协议      VLESS        传输 TCP        TLS 关闭（REALITY 自带安全层，开着也不生效）
Flow      xtls-rprx-vision
dest      <借用的真站>:443
SNI       必须与 dest 同站 —— 面板会校验
```

选 dest：

```bash
bash tools/dest-candidates.sh              # 生成候选（面板上也有「自动生成候选」按钮）
# 候选填进节点的「dest 候选清单」→ 保存 → 节点自动扫 → 结果表点「用最优」
destprobe -vps <节点IP> -d 30s <候选…>     # 可选：深度探测，必须【在节点上】跑
```

`[!!]` **人工必须过一遍**，三件事脚本判不了：金融/保险/政府机构（不该冒充）、
站还活不活着、以及「这个 SNI 出现在你这个 IP 上自不自然」。
详见 `compatibility/dest-scan.md`（sogacore 仓库）。

---

## 3. 域名与 DNS

```
面板       app.<域A>          → Cloudflare Tunnel（橙云）
后台       admin.<域A>        → 同上；ADMIN_HOST 填它
入口       cdn.<域B>          → CNAME → hk1.<域B>   TTL 600
标签       hk1.<域B>          → A     → 节点 IP     TTL 60  【灰云】
```

`[!!]` **入口域名必须与面板域名分属不同的【可注册域】**（D-4）。封禁作用在整个可注册域上，
入口被封时面板不能跟着一起死。面板的自检页会检查这一条。

`[!!]` **入口域名的 DNS 记录必须关掉小黄云（DNS only）。** 开着它会把流量当 HTTP 代理，
而 vless 是裸 TCP —— 表现是「域名能解析、就是连不上」，很难查。

`[!]` 两层 CNAME 的意义：换 IP 时只改 `hk1` 那一条 A 记录，所有挂在同一标签下的
门牌一起生效。见 `docs/decisions/entry-dispatch.md`（D-5）。

---

## 3a. `[!!]` 绝不要在跑测试前 `config:cache`

`[D]` 2026-09-24 实际发生过一次：`php artisan config:cache` 之后再
`php artisan test`，`RefreshDatabase` 要在**生产库 `vpn`** 上跑
`migrate:fresh`。

原因：`phpunit.xml` 里的

```xml
<env name="DB_DATABASE" value="vpn_test"/>
```

**盖不住配置缓存** —— Laravel 一旦读 `bootstrap/cache/config.php`
就不再看环境变量，而那个文件是用 `.env`（`DB_DATABASE=vpn`）生成的。

`[D]` 拦住它的是 `AppServiceProvider.php` 里那道
「拒绝在非 `_test` 库上执行 migrate/tinker」的护栏。**没有它，生产库
会被整个清空重建，而且跑完一片绿 —— 现象是没有现象。**

```bash
# 跑测试之前
docker compose exec app php artisan config:clear

# 只在部署完、确认不再跑测试时才 cache
docker compose exec app php artisan config:cache
```

`[!]` 那道护栏当初是为 `tinker` 加的，这次替 `php artisan test` 挡了一次。
**别以为它多余。**

---

## 3b. `[!!]` 换服务器时 DNS 不用动

`[S]` `cloudflared` 用 token 认身份（`tunnel run --token ${CLOUDFLARE_TUNNEL_TOKEN}`），
而 DNS 指向的是 `<隧道ID>.cfargotunnel.com` —— **不是服务器 IP**。
所以**哪台机器拿着那个 token，它就是隧道的出口**。

```
不用动   所有 DNS 记录（面板 / 后台 / 订阅）
         Tunnel 里的 public hostnames
要做     ① CLOUDFLARE_TUNNEL_TOKEN 原样搬到新机器的 .env
         ② 恢复数据库
         ③ 重建 agent 与 destprobe 二进制到 public/agent/v1（不在 git 里）
         ④ 轮换节点密钥（旧机器上的一律视为已泄露）
         ⑤ 【关掉旧机器的 cloudflared】
```

`[!!]` **⑤ 最容易忘，而它的表现很怪：** 同一个 token 跑在两台机器上时，
Cloudflare 会把请求**在两边分流** —— 一半用户打到新机器、一半打到还带着旧数据的老机器。
**不报错，只是行为随机。** 迁移期间务必确认只有一边在跑。

`[!]` **入口域名是例外。** `hk1.<入口域>` 是**灰云 A 记录直指节点 IP**
（代理流量必须直连节点，不能走隧道），换节点机器时必须改那条 A 记录。
但那是节点，不是面板 —— 两者本来就该是不同的机器（见 §0）。

`[i]` 换个说法：**面板的"地址"是隧道，节点的"地址"是 IP。**
前者搬机器不用改 DNS，后者必须改。

---

## 4. 上线前必做

按 `docs/LAUNCH-CHECKLIST.md` 走。搬机器时**额外**要做的：

```
□ 换掉所有密钥        节点 secret、APP_KEY、数据库密码 —— 旧机器上的都视为已泄露
□ 验证备份能恢复      不是"跑通了备份脚本"，是"拿备份真的恢复出一个能登录的面板"
□ 关掉旧机器上的 agent 否则它会带着旧凭据继续打新面板（[D] 见清单 L-11 那个僵尸）
□ APP_ENV=production  等支付走通之后
```

---

## 5. 怎么确认真的成了

```bash
# 面板
curl -sI https://<面板域名>/ | head -1                    # 200
curl -s https://<面板域名>/sub/<某用户token> | head -5     # 有节点条目

# 后台（隧道内）
ssh -L 18088:127.0.0.1:18088 <面板服务器>
# 浏览器 http://localhost:18088/admin → 自检页应只剩已知的黄/红

# 节点
journalctl -u agent -n 20 --no-pager
#   期望看到 core started port=… protocol=vless security=reality
ss -lntp | grep <节点端口>

# REALITY 伪装（从任意第三方机器）
openssl s_client -connect <入口域名>:<端口> -servername <SNI> </dev/null 2>/dev/null \
  | grep -E '^subject|Verify return'
#   期望：拿到【dest 真站】的证书，Verify return code: 0 (ok)
openssl s_client -connect <入口域名>:<端口> -servername nosuch.invalid </dev/null 2>&1 | grep ^subject
openssl s_client -connect <dest>:443       -servername nosuch.invalid </dev/null 2>&1 | grep ^subject
#   期望：这两行【一模一样】—— 错误 SNI 下也和真站无法区分，才叫伪装成功
```

`[!!]` 最后那组对照是整套验证里最有价值的一条：**只验正常路径不够**，
探测者会拿错误的 SNI 来试，那时你的节点必须和真站给出同样的回应。

---

## 6. 已知会绊人的

| 现象 | 实因 |
|---|---|
| 后台 404 | `ADMIN_HOST` 与访问的主机名不符；或走了公网口而不是 18088 |
| 节点心跳正常但用户连不上 | dest 连不上（`destHealth` 会红），或 `server_type` 与面板协议不符 |
| 改了协议却没生效 | `agent.conf` 的 `server_type` 没跟着改（§2.2） |
| 同机装第二个节点"成功"但没心跳 | 旧版 installer 的静默跳过；新版会明确拒绝 |
| 域名能解析但连不上 | 入口域名开着 Cloudflare 小黄云 |
| 备份"一直在跑"却没有文件 | 参考 `tools/backup.sh` 头部那段：只在失败时沉默的备份等于没有 |
| `php -l` 报 `?->` 语法错 | 宿主 PHP 是 7.4；lint 走 `docker compose exec app php -l` |

---

## 7. 这份文档没有覆盖的

- `[?]` **多节点 / 中转拓扑**：本项目目前只跑过单落地。中转的设计在
  `docs/decisions/relay-boundaries.md`，代码路径有测试，但**没有真机多节点部署经验**。
- `[?]` **水平扩容**：面板是单机 docker compose。没做过多实例、没测过。
- `[!]` **iOS / macOS 客户端**尚未上线，那两端用户走第三方客户端
  （小火箭 / Clash），不受设备数限制 —— 见 `docs/decisions/device-model.md` §2a。
