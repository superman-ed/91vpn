# 02 ·(保姆版样板)快速开始 —— 从零跑通一个节点

> **保姆版**:零假设。每一步先说**这步在干嘛**,再给命令 → ✅对了吗 → 没对怎么办。
> 纯文字。约 30 分钟。全程用真实命令,已在全新 `git clone` 上跑到"能打开面板"。
> 不懂词先看 [00 · 术语表](00-glossary.md);想先了解全貌看 [01 · 系统总览](01-overview.md)。

---

## 开始前 · 你要准备什么

### 你得会的两件事
- **会用 SSH 登录 Linux**(`ssh root@你的IP`)。不会 → 先学这个再回来。
- **知道 Docker 是干嘛的**:它把整套服务打包成几个"容器"一键跑起来,你不用手装 PHP/MySQL(只面板机需要)。

### 你要买的服务器 —— 三种角色,配置差很多

第一次跑通,最少要 **1 台面板 + 1 台落地**(2 台);要"用户连着快"就再加 **1 台中转**(3 台)。
系统 **一律 Debian/Ubuntu 最新 LTS**(命令都按这个来)。

| 角色 | 干嘛的 | CPU/内存 | 磁盘 | **月流量** | 端口速率 | **线路/地区** |
|---|---|---|---|---|---|---|
| **面板机** | 跑后台(Laravel+MySQL+Redis) | 1核1G 够,2核2G 舒服 | 20–40G | 很小(只走 API/订阅) | 无所谓 | **随便挑便宜稳的**——它走 CF 隧道出去,不承载用户流量,地区不重要 |
| **落地** | 用户流量最终从这出去上网 | 起步 1核1G,按人数加 | 20G | **关键成本**:看套餐给多少(便宜机常限 1TB/月),用户很费流量 | 越大越好(100M/1G) | 你想要的**出口国家**(日本/美国/…);到中转路由过得去即可,**可以便宜、可弃** |
| **中转**(可选但强烈建议) | 用户连的入口,过墙那一跳 | 1核1G(只转发,CPU 轻) | 20G | 同落地,按人数 | 越大越好 | **这里花钱买中国优化线**:CN2 GIA / CMIN2 / 9929,港/日/近亚洲,延迟越低越好 |

**几条要点(为什么这么配)**:
- 💰 **钱花在中转的线路上,不是 CPU**。代理转发很吃网络、不太吃 CPU;决定用户体验的是**中转那条线干不干净、快不快**。
- 🎭 **中转优质线 ≠ 不用伪装**。优质线只是快,IP 照样会被封 → 我们**中转走优质线 + 落地开 REALITY 伪装**两者叠加(见 [06](06-reality.md))。落地被封了换台便宜机重装即可,所以落地"可弃"。
- 📊 **月流量是隐形大坑**:很多低价机月流量只有 500G–1T,几十个活跃用户几天就跑光,超了要么限速要么加钱。买之前一定看清"流量/带宽"那栏。
- 🧠 **内存按人数涨**:1G 内存 agent 撑几百用户没问题(它按可用内存 75% 自调);上千用户再加内存。
- 🖥 **面板机可以最便宜**:它只处理登录/订阅/管理这类小请求,不过用户流量;而且我们让它**只经 CF 隧道对外、不开公网端口**(见 [03](03-panel-deploy.md))。

> 起步省钱方案:面板机用最便宜的欧美/香港小鸡;落地用便宜大流量机(挑你要的出口国);中转用一台**带 CN2 GIA/CMIN2/9929 的港机**。先 2 台(面板+落地)跑通协议,再加中转提速。

### 域名
一个就行,**NS 托管在 Cloudflare**(隧道 + Access 要用)。便宜 TLD 无所谓。第 6 步才用到,先跑通可以不要。

---

## 0. 先确认 Docker 在

🎯 **这步在干嘛**:确认面板机能跑容器,不然后面全跑不起来。

```bash
docker --version && docker compose version
```
**✅ 对了吗**:看到两个版本号就行。
**没对**:按官方文档装 <https://docs.docker.com/engine/install/>。
`[!]` 是 `docker compose`(带空格),不是老的 `docker-compose`。

---

## 1. 取代码

🎯 **这步在干嘛**:把面板代码拉到面板机上。

```bash
git clone <仓库地址> 91vpn
cd 91vpn
```
**✅ 对了吗**:目录里有 `docker-compose.yml`、`artisan`、`app/`。
`[!]` 没有 `vendor/` 是正常的(PHP 依赖不进版本库,第 3 步装)。

---

## 2. 配置

🎯 **这步在干嘛**:生成你自己的配置文件 `.env`,先用默认值跑通。

```bash
cp .env.example .env
```
默认值对应 compose 里的数据库,**先原样跑通再改**。现在只确认两项:

| 键 | 先填什么 |
|---|---|
| `APP_URL` | `http://localhost:8088`(本地跑通用;上域名后再改) |
| `ADMIN_HOST` | **留空**。`[!!]` 现在填了你自己也进不去后台 |

---

## 3. 起容器、装依赖

🎯 **这步在干嘛**:把 6 个服务(面板/数据库/缓存/定时任务/网页/隧道)一起拉起来,再装 PHP 依赖。

```bash
docker compose up -d
docker compose ps
```
**✅ 对了吗**:6 个容器都在:
```
app         running
cloudflared restarting     ← 正常,它还没 token,第 6 步才配
db          running
redis       running
scheduler   running
web         running
```
`[!]` `cloudflared` 反复重启是正常的(没 token),不影响其余。

```bash
docker compose exec app composer install
```
**✅ 对了吗**:一堆包名后跟 `DONE`,最后一行 `xx packages ... looking for funding`。
**装了几分钟没动静**:多半网络问题,换 composer 镜像源。

---

## 4. 初始化数据库

🎯 **这步在干嘛**:建表、生成应用密钥、建第一个管理员账号。

```bash
docker compose exec db mysqladmin ping -proot     # 等数据库起来
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=AdminSeeder --force
```
**✅ 对了吗**:
```
mysqld is alive
INFO  Application key set successfully.
...  ..._add_relay_telemetry_to_nodes ... DONE
INFO  Seeding database.
```
**`migrate` 报连不上数据库**:等 20 秒再跑一次(MySQL 首次启动初始化,比容器"Started"晚)。
**报 `Please provide a valid cache path`**:补目录 `mkdir -p storage/framework/{cache/data,sessions,views} storage/logs`。

---

## 5. 打开面板

🎯 **这步在干嘛**:面板只绑回环(不对公网开),本地先用 SSH 隧道看一眼。

```bash
# 在你自己的电脑上执行
ssh -L 8088:127.0.0.1:8088 -L 18088:127.0.0.1:18088 <面板机>
```
浏览器打开:

| 地址 | 应该看到 |
|---|---|
| `http://localhost:8088/login` | 用户登录页 |
| `http://localhost:8088/admin` | **404** —— 对的,公网口不放后台 |
| `http://localhost:18088/admin` | 跳到登录页 |

用刚才种子建的账号登录后台:**账号 `admin` / 密码 `password`**。

`[!!]` **立刻改密码**——这是写死在代码里的公开默认口令,谁都查得到。
改法:后台**右上角的 🔑 图标** → 填当前密码 + 新密码 → 保存。

---

## 6. 挂上域名(可选,但上线前必做)

🎯 **这步在干嘛**:本地隧道只有你能用;要让节点和用户访问,得配 Cloudflare Tunnel。

见 [03 · 面板部署](03-panel-deploy.md) 的 Cloudflare Tunnel 一节——那里讲两个主机名怎么分、Access 为什么**不能**套在用户面上。跑通之前可先跳过,继续用 SSH 隧道。

---

## 7. 建一个节点

🎯 **这步在干嘛**:在面板里登记一台节点,拿到装节点要用的密钥。

后台 →「节点管理」→「添加节点」:

| 字段 | 填什么 | 为什么 |
|---|---|---|
| 名称 | 如 `日本01` | 会显示在用户订阅里 |
| **角色** | **落地** | 中转是另一套,见 [05](05-relay.md) |
| 地址 | 节点机的公网 IP | 用户要连的地址 |
| 端口 | 如 `443` | |
| 协议 / 传输 | `VMess` / `TCP` | **先跑通最简形态**,REALITY 等跑通了再上([06](06-reality.md)) |
| 倍率 | `1` | 计费倍率 |
| 等级门槛 | `0` | 0 = 所有用户可见 |

保存后回列表点「编辑」,页面上有两样要用:
- **通信密钥(secret)**——装节点时填;
- **用户名单接口**——一条拼好的完整 URL,形如 `<面板地址>/mod_mu/users?node_id=12&key=<secret>`。

`[!]` 把第二条粘进浏览器或 `curl`,**当场就能验证面板这侧通不通**,不必等装完节点再猜。看到 `{"ret":1,"data":[...]}` 就对了。
`[!!]` 这个 secret = 节点身份,**别贴进聊天/工单/截图**。

---

## 8. 装节点

### 先决条件:把 agent 二进制发布到面板

🎯 **这步在干嘛**:节点要从面板下载 agent,先把它编译好放到面板能提供下载的目录。

**在 sogacore 仓库所在的机器上**执行:
```bash
bash tools/publish-agent.sh /path/to/91vpn
```
**✅ 对了吗**:
```
==> 编译 linux/amd64
==> 编译 linux/arm64
已发布到 .../public/agent/v1：
    agent-linux-amd64   25M
    agent-linux-arm64   23M
    install.sh          11K
```
`[!]` 用 Docker 里的 Go,**你不用装 Go**(首次拉镜像几分钟)。
验证能下载(面板起着):`curl -sI <面板地址>/agent/v1/install.sh`(应 200)。
`[!]` 这目录在 `.gitignore` 里(产物不进库)→ **换机器部署面板后要重新发布一次**。

### 方式一:后台一键部署(推荐)

节点列表 →「🚀部署」→ 填 SSH 主机/端口/用户 + 私钥正文。面板 SSH 进去让目标机自己下载安装,日志实时回显,跑到 `✅ 部署完成` 即成。

### 方式二:手工

🎯 在**节点机**上:
```bash
# 把 secret 写进文件(别用 --api-key,那会进 ps,同机任何用户可见)
umask 077 && printf '%s' '<粘贴 secret>' > /root/node.secret

curl -fsSL <面板地址>/agent/v1/install.sh -o /tmp/install.sh
bash /tmp/install.sh \
  --base-url <面板地址>/agent/v1 \
  --panel sspanel-uim \
  --api-url <面板地址> \
  --node-id <节点ID> \
  --api-key-file /root/node.secret \
  --server-type vmess
```
`[!]` `--base-url` 不能省(脚本靠它按本机架构拼下载地址);少了报 `缺 --binary / --binary-url / --base-url`。
`[!]` 重装同一台加 `--force-conf`,否则保留已有配置不动(升级不该顺手改掉你调过的配置)。
**✅ 对了吗**:最后出现 `✅ 安装完成，节点已就绪`。

---

## 8b. `[!!]` 放行节点端口 —— 最容易漏的一步

🎯 **这步在干嘛**:装完 agent ≠ 用户能连。那个端口要在**防火墙**放行,而且有**两层**。

**① 机器自己的防火墙**:
```bash
ufw status                 # 开着的话:
ufw allow 39500/tcp        # 放行你在面板给这节点配的端口
```
没装 ufw 看 iptables:`iptables -S INPUT | head`。

**② 云厂商的安全组**:阿里云/腾讯云/AWS/GCP 在机器之外还有一层,要去**控制台**放行同一端口。机器里 `ufw status` 显示 inactive **也不代表通**——那层在机器外面。

**为什么最坑**:`[!!]` 端口没放行时**所有迹象都显示正常**——`agent` active、`/ready` 可服务、面板显示**在线**(心跳是往外发的,不需入站放行)。只有客户端连不上。

**怎么确认**(从**你自己的电脑**,不是节点上):
```bash
nc -zv <节点IP> <端口>
```
连得上 = 这层通了;连不上 → 先查机器 ufw,再查云控制台。
`[!]` 我们自己踩过一次,前面每项检查都绿,最后才想到防火墙。

---

## 9. 确认接上了

🎯 **这步在干嘛**:两头对一遍——节点侧起来了、面板侧看到它在线。

**节点机上**:
```bash
systemctl is-active agent          # active
curl -s 127.0.0.1:9090/ready       # {"ready":true}
journalctl -u agent -n 20 --no-pager
```
日志应有 `agent started` 和 `core started`(后者带端口和协议,核对与面板填的一致)。
`[!]` REALITY 节点刚重启头十几秒 `/ready` 可能 503(要先探一次 dest),等一周期;一直 503 才是问题。vmess 节点没这问题。

**面板上**:节点列表那台显示**在线**,心跳 60 秒内。

**没上线怎么查**(每步都在**节点机**上跑):
```bash
journalctl -u agent -n 50 --no-pager | grep -iE "error|fail"      # 1. agent 自己怎么说
curl -sI "<面板地址>/mod_mu/nodes/<节点ID>/info?key=<secret>"     # 2. 节点能不能打通面板
```
| 返回 | 意思 |
|---|---|
| `200` | 通了,问题在 agent 侧,回看第 1 步 |
| `401` | secret 或 node_id 不对(两者返回一样,逐个核对) |
| `403` | 多半 Cloudflare 机器人检测拦了(见 [03](03-panel-deploy.md)) |
| 连不上 | 面板地址填错,或节点**出站**被墙/防火墙 |

---

## 10. 建用户、拿订阅、连上

🎯 **这步在干嘛**:走一遍真实用户的路,验证端到端。

1. 后台「用户管理」新增,或前台自己注册一个;
2. 用该用户登录**前台**,「我的节点」页有订阅链接;
3. 把链接导入客户端(Mihomo / v2rayN / Clash);
4. 选刚才那个节点,连。

**✅ 成了** = 能上网。连不上:客户端日志调到 `info` 再看(很多客户端默认 `warning`,会把真正的拒绝原因藏起来)。

---

## 跑通之后

| 想做什么 | 去哪 |
|---|---|
| 挂正式域名、加后台保护 | [03 · 面板部署](03-panel-deploy.md) |
| 上 REALITY(抗封锁) | [06 · REALITY 上线](06-reality.md) |
| 加中转(用户连香港、落地在日本) | [05 · 中转架构](05-relay.md) |
| 节点配置项都有什么 | [04 · 节点部署](04-node-deploy.md) |
| 出了问题怎么查 | [09 · 运维手册](09-ops.md) |
