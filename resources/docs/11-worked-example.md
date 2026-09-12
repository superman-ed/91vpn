# 11 · 完整演练（全部用具体数值）

其余章节为了通用性用了 `<面板地址>` 这样的占位符。这一篇**不用任何占位符** ——
从头到尾是一次真实部署的完整记录，所有值都写死。

照着读一遍，你就知道每个占位符该换成什么样的东西。

---

## 0. 本次演练的设定

| 东西 | 本例的值 | 你的情况 |
|---|---|---|
| 域名 | `example.com` | 换成你买的那个 |
| 面板机 | 一台 Ubuntu，公网 IP `203.0.113.10` | 你的面板机 |
| 节点机 | 一台 Debian，公网 IP `198.51.100.20` | 你的节点机 |
| 用户面地址 | `https://app.example.com` | `app.<你的域名>` |
| 后台地址 | `https://admin.example.com` | `admin.<你的域名>` |
| 节点在面板里的 ID | `3` | 建完节点后面板会告诉你 |
| 节点通信密钥 | `kJ8xQ2mN...`（32 位随机串） | 节点编辑页上能看到，**别外传** |
| 节点监听端口 | `39500` | 你自己定，记得在防火墙放行 |

`[!]` `203.0.113.x` / `198.51.100.x` / `example.com` 都是文档专用的保留地址，
不是真实服务器 —— 照抄这些值不会连到任何东西。

---

## 1. 占位符对照表

其余章节里出现的占位符，对应本篇的哪个值：

| 占位符 | 什么意思 | 本例的值 |
|---|---|---|
| `<仓库地址>` | 面板代码的 git 地址 | 你拿到的那个 |
| `<面板地址>` | **用户面**的完整 URL，带 `https://` | `https://app.example.com` |
| `<面板机>` | 面板那台机器（SSH 用） | `root@203.0.113.10` |
| `<节点>` / `<节点IP>` | 节点那台机器 | `198.51.100.20` |
| `<节点ID>` | 节点在面板里的数字 ID | `3` |
| `<secret>` | 节点通信密钥 | 节点编辑页上那串 |
| `<端口>` | 节点监听端口 | `39500` |

`[!!]` 最容易搞混的是 `<面板地址>`：它是**用户面**那个（`app.`），
不是后台那个（`admin.`）。填成后台的话，节点每次请求都会撞上 Access 登录页。

---

## 2. 面板机上

```bash
ssh root@203.0.113.10

git clone <你拿到的仓库地址> 91vpn
cd 91vpn
cp .env.example .env
```

编辑 `.env`，改这三行：

```
APP_URL=https://app.example.com
ADMIN_HOST=admin.example.com
CLOUDFLARE_TUNNEL_TOKEN=eyJhIjoixxxxx...     # 第 3 步拿到后再填
```

起容器、装依赖、初始化：

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec db mysqladmin ping -proot          # 等它说 mysqld is alive
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=AdminSeeder --force
```

---

## 3. Cloudflare 上

按 [03 §2](03-panel-deploy.md) 操作。本例中填的值：

**两条 Published application routes**（同一条隧道）：

| Subdomain | Domain | Type | URL |
|---|---|---|---|
| `admin` | `example.com` | HTTP | `http://web:8080` |
| `app` | `example.com` | HTTP | `http://web:80` |

**一个 Access 应用**：Application domain 填 `admin.example.com`，Path 留空。

拿到 token 后回面板机：

```bash
nano .env                      # 填 CLOUDFLARE_TUNNEL_TOKEN
docker compose up -d cloudflared
```

验证：

```bash
curl -sI https://app.example.com/login      # 200
curl -sI https://app.example.com/admin      # 404
curl -sI https://admin.example.com/         # 302
```

---

## 4. 发布 agent 二进制

在有 sogacore 代码的机器上（可以就是面板机）：

```bash
bash tools/publish-agent.sh /root/91vpn
curl -sI https://app.example.com/agent/v1/install.sh    # 200
```

---

## 5. 在面板里建节点

浏览器打开 `https://admin.example.com`（会先过 Cloudflare 的邮箱验证），
用 `admin` / `password` 登录，**立刻改密码**。

节点管理 → 添加节点：

| 字段 | 本例填 |
|---|---|
| 名称 | `日本01` |
| 角色 | 落地 |
| 地址 | `198.51.100.20` |
| 端口 | `39500` |
| 协议 / 传输 | `vmess` / `tcp` |
| 倍率 | `1` |
| 等级门槛 | `0` |

保存 → 编辑，记下：**节点 ID = 3**、**secret = `kJ8xQ2mN...`**。

编辑页上还有一条拼好的「用户名单接口」，粘进浏览器应当返回
`{"ret":1,"data":[...]}` —— 这一步确认了面板侧是通的。

---

## 6. 装节点

```bash
ssh root@198.51.100.20

umask 077 && printf '%s' 'kJ8xQ2mN...' > /root/node.secret

curl -fsSL https://app.example.com/agent/v1/install.sh -o /tmp/install.sh
bash /tmp/install.sh \
  --base-url https://app.example.com/agent/v1 \
  --panel sspanel-uim \
  --api-url https://app.example.com \
  --node-id 3 \
  --api-key-file /root/node.secret \
  --server-type vmess
```

---

## 7. 放行端口

```bash
# 还在节点机上
ufw status                     # 开着的话:
ufw allow 39500/tcp
```

云厂商控制台的安全组也要放行 `39500/tcp`。

**从你自己的电脑**验证：

```bash
nc -zv 198.51.100.20 39500
```

---

## 8. 验收

节点机上：

```bash
systemctl is-active agent      # active
curl -s 127.0.0.1:9090/ready   # {"ready":true}
```

面板后台 → 节点管理：`日本01` 显示**在线**。

---

## 9. 建用户、连上

后台 → 用户管理 → 新增，或前台 `https://app.example.com/register` 注册。

用该账号登录前台 → 「我的节点」→ 复制订阅链接
（形如 `https://app.example.com/sub/aBcD1234...`）→ 导入客户端 → 连。

---

## 做完了

到这里你有了：一个能自助注册的用户面板、一个在线节点、一条能用的订阅。

接下来按需要选：

| 想做什么 | 去哪 |
|---|---|
| 抗封锁（REALITY） | [06](06-reality.md) |
| 加中转 | [05](05-relay.md) |
| 节点配置项详解 | [04](04-node-deploy.md) |
| 出问题了 | [09](09-ops.md) |
