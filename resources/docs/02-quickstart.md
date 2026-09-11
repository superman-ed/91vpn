# 02 · 快速开始（从零搭建）

面向**第一次接触这套系统的人**。每一步都给出：命令 → 你应该看到什么 →
没看到怎么办。

**实测过**：本文的命令在一个全新 `git clone` 上从头跑过一遍，到"能打开面板"为止。

---

## 0. 你需要准备什么

| 东西 | 要求 | 说明 |
|---|---|---|
| 面板机 | 一台 Linux，装了 Docker 与 Docker Compose | 1 核 1G 够用 |
| 节点机 | 一台 Linux VPS（`amd64` 或 `arm64`） | 用户实际连的机器 |
| 域名 | 一个，NS 托管在 Cloudflare | 第 6 步才用得到，先跑通可以不要 |

检查 Docker 在不在：

```bash
docker --version && docker compose version
```

看到两个版本号就行。没有的话按官方文档装：<https://docs.docker.com/engine/install/>

`[!]` 是 `docker compose`（带空格）不是 `docker-compose`。老版本的写法在新版里已经废弃。

---

## 1. 取代码

```bash
git clone <仓库地址> 91vpn
cd 91vpn
```

**应该看到**：目录里有 `docker-compose.yml`、`artisan`、`app/` 等。

`[!]` `vendor/` 目录**不在**仓库里（PHP 依赖不进版本库），第 3 步会装。

---

## 2. 配置

```bash
cp .env.example .env
```

`.env.example` 里的默认值对应 compose 里的数据库服务，**先原样跑通再改**。
现在只需要确认两项：

| 键 | 先填什么 |
|---|---|
| `APP_URL` | `http://localhost:8088`（本地跑通用；上域名后再改） |
| `ADMIN_HOST` | **留空**。`[!!]` 现在填了的话你自己也进不去后台 |

---

## 3. 起容器、装依赖

```bash
docker compose up -d
```

**应该看到**：六个容器 `Started`。`docker compose ps` 确认：

```
app         running
cloudflared restarting     ← 正常，见下
db          running
redis       running
scheduler   running
web         running
```

`[!]` `cloudflared` 这个会反复重启 —— 正常，它还没有 token，第 6 步才配。
不影响其余服务。

```bash
docker compose exec app composer install
```

**应该看到**：一堆包名后面跟着 `DONE`，最后是 `xx packages you are using are looking for funding`。

装了几分钟还没动静：多半是网络问题，换 composer 镜像源。

---

## 4. 初始化数据库

```bash
# 等数据库起来（第一次会慢一点）
docker compose exec db mysqladmin ping -proot

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=AdminSeeder --force
```

**应该看到**：

```
mysqld is alive
INFO  Application key set successfully.
...  2026_09_09_210000_add_relay_telemetry_to_nodes ... DONE
INFO  Seeding database.
```

**如果 `migrate` 报连不上数据库**：等 20 秒再跑一次 —— MySQL 第一次启动要初始化，
比容器"Started"晚。

**如果报 `Please provide a valid cache path`**：`storage/framework/` 下的目录没了。
正常 clone 不会遇到；如果你是拷贝目录过来的，补一下：

```bash
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs
```

---

## 5. 打开面板

面板容器**只绑回环**（`127.0.0.1:8088` 用户面、`127.0.0.1:18088` 后台）——
对外一律经隧道，这是刻意的。本地先看的话开个 SSH 隧道：

```bash
# 在你自己的电脑上执行
ssh -L 8088:127.0.0.1:8088 -L 18088:127.0.0.1:18088 <面板机>
```

然后浏览器打开：

| 地址 | 应该看到 |
|---|---|
| `http://localhost:8088/login` | 用户登录页 |
| `http://localhost:8088/admin` | **404** —— 对的，公网口不放后台 |
| `http://localhost:18088/admin` | 跳到登录页 |

用 `AdminSeeder` 建的账号登录后台：**账号 `admin` / 密码 `password`**。

`[!!]` **立刻改密码** —— 这是写在代码里的公开默认口令，谁都查得到。
后台右上角 →「账号」→ 修改密码。

---

## 6. 挂上域名（可选，但上线前必做）

本地隧道只能你自己用。要让节点和用户访问，见
[03 · 面板部署](03-panel-deploy.md) 的 Cloudflare Tunnel 一节 ——
那里讲了两个主机名怎么分、Access 为什么**不能**套在用户面上。

跑通之前可以先跳过，用 SSH 隧道继续往下。

---

## 7. 建一个节点

后台 →「节点管理」→「添加节点」：

| 字段 | 填什么 | 为什么 |
|---|---|---|
| 名称 | 如 `日本01` | 会显示在用户订阅里 |
| **角色** | **落地** | 中转是另一套，见 [05](05-relay.md) |
| 地址 | 节点机的公网 IP | 用户要连的地址 |
| 端口 | 如 `443` | |
| 协议 / 传输 | `vmess` / `tcp` | **先跑通最简形态**，REALITY 等跑通了再上 |
| 倍率 | `1` | 计费倍率 |
| 等级门槛 | `0` | 0 = 所有用户可见 |

保存后回到列表，点「编辑」。页面上有两样东西要用：

- **通信密钥（secret）** —— 装节点时要填
- **用户名单接口** —— 一条拼好的完整 URL，形如
  `<面板地址>/mod_mu/users?node_id=12&key=<secret>`

`[!]` 第二条很有用：把它粘进浏览器或 `curl`，**当场就能验证面板这一侧是通的**，
不必等装完节点再来猜是哪边的问题。看到 `{"ret":1,"data":[...]}` 就对了。

`[!!]` 这个 secret 等于这个节点的身份。**不要贴进聊天、工单、截图。**

---

## 8. 装节点

### 先决条件：把 agent 二进制发布到面板

不论哪种装法，节点都要从面板下载 agent。**在 sogacore 仓库所在的机器上**执行：

```bash
bash tools/publish-agent.sh /path/to/91vpn
```

**应该看到**：

```
==> 编译 linux/amd64
==> 编译 linux/arm64
已发布到 .../public/agent/v1：
    agent-linux-amd64        25M
    agent-linux-arm64        23M
    install.sh               11K
```

`[!]` 用的是 Docker 里的 Go，**你不需要装 Go** —— 第一次会拉 `golang` 镜像，
几分钟。

验证能下载（面板起着的话）：

```bash
curl -sI <面板地址>/agent/v1/install.sh    # 应当 200
```

`[!]` 这个目录在面板仓库的 `.gitignore` 里 —— 构建产物不进版本库。
换句话说**换台机器部署面板后要重新发布一次**。

### 方式一：后台一键部署（推荐）

节点列表 →「部署」按钮 → 填 SSH 主机/端口/用户 + 私钥正文或密码。
面板会 SSH 进去，让目标机自己下载并安装。

### 方式二：手工

在**节点机**上：

```bash
# 把面板节点页的 secret 写进文件（不要用 --api-key，那会进 ps，同机任何用户都看得到）
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

`[!]` `--base-url` 不能省 —— 脚本靠它按本机架构（amd64/arm64）拼出下载地址。
少了它会直接报 `缺 --binary / --binary-url / --base-url`。

`[!]` 重装同一台时加 `--force-conf`，否则脚本会保留已有配置不动
（那是刻意的:升级 agent 不该顺手改掉运维改过的配置）。

**应该看到**：

```
==> 拉取二进制: ...
==> 安装 git-xxxxxxx (go1.xx, built ...)
==> 已写入 /etc/agent/agent.conf（0600）
==> 等待就绪
✅ 安装完成，节点已就绪
```

---

## 8b. `[!!]` 放行节点端口 —— 最容易漏的一步

装完 agent 不等于用户能连上。**节点那个端口要在防火墙放行**，两处都要看：

### 机器自己的防火墙

```bash
# 看有没有开着 ufw
ufw status

# 开着的话，放行你在面板里给这个节点配的端口
ufw allow 39500/tcp
```

没装 ufw 的机器检查 iptables：`iptables -S INPUT | head`。

### 云厂商的安全组

阿里云/腾讯云/AWS/GCP 这些在**机器之外**还有一层安全组或防火墙规则，
要去控制台放行同一个端口。机器里 `ufw status` 显示 inactive 也不代表通 ——
那一层在机器外面，机器里看不见。

### 为什么这步最坑

`[!!]` 端口没放行时，**所有迹象都显示一切正常**：

- `systemctl is-active agent` → active
- `curl 127.0.0.1:9090/ready` → 可服务
- 面板节点列表 → **在线**（心跳是节点主动往外发的，不需要入站放行）

只有客户端连不上。于是人会去查客户端配置、查订阅、查节点协议 ——
而问题在一个谁都没看的地方。

### 怎么确认

**从你自己的电脑**（不是节点上）：

```bash
nc -zv <节点IP> <端口>
# 或
curl -sI --max-time 5 telnet://<节点IP>:<端口>
```

连得上就说明这一层通了。连不上：先查机器里的 ufw，再查云厂商控制台。

`[!]` 我们自己踩过一次：端口被 UFW 挡着，排查了很久才想到看防火墙 ——
因为前面每一项检查都是绿的。

---

## 9. 确认接上了

**在节点机上**：

```bash
systemctl is-active agent          # active
curl -s 127.0.0.1:9090/ready       # {"ready":true}
journalctl -u agent -n 20 --no-pager
```

日志里应该有 `agent started` 和 `core started`（后者会带上端口与协议，
核对一下和你在面板里填的是否一致）。

`[!]` 刚重启的头十几秒 `/ready` 可能返回 **503**，这是正常的 ——
REALITY 节点要先探一次 dest 才算"可服务"。等一个周期再看；
一直 503 才是问题（见 [09](09-ops.md)）。

**在面板上**：节点列表里那台显示**在线**，心跳 60 秒内。

### 没上线怎么查

按这个顺序，**每一步都在节点机上执行**：

```bash
# 1. agent 自己怎么说
journalctl -u agent -n 50 --no-pager | grep -iE "error|fail"

# 2. 从节点能不能打通面板（注意:要在节点上跑,不是你的电脑上）
curl -sI "<面板地址>/mod_mu/nodes/<节点ID>/info?key=<secret>"
```

| 返回 | 意思 |
|---|---|
| `200` | 通了，问题在 agent 侧，回看第 1 步 |
| `401` | secret 不对，**或** node_id 不对 —— 两种情况返回的东西一样，逐个核对 |
| `403` | 多半是 Cloudflare 的机器人检测拦了（见 [03](03-panel-deploy.md)） |
| 连不上 | 面板地址填错，或节点**出站**被墙/防火墙 |

---

## 10. 建用户、拿订阅、连上

1. 后台 →「用户管理」→ 新增；或前台自己注册一个
2. 用该用户登录**前台**，「我的节点」页有订阅链接
3. 把链接导入客户端（Mihomo / v2rayN / Clash 等）
4. 选中刚才那个节点，连

连不上时：客户端日志调到 `info` 级别再看 ——
`[!]` 很多客户端默认 `warning`，会把真正的拒绝原因藏起来。

---

## 跑通之后

| 想做什么 | 去哪 |
|---|---|
| 挂正式域名、加后台保护 | [03 · 面板部署](03-panel-deploy.md) |
| 上 REALITY（抗封锁） | [06 · REALITY 上线](06-reality.md) |
| 加中转（用户连香港、落地在日本） | [05 · 中转架构](05-relay.md) |
| 节点配置项都有什么 | [04 · 节点部署](04-node-deploy.md) |
| 出了问题怎么查 | [09 · 运维手册](09-ops.md) |
