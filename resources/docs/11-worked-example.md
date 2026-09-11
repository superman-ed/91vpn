# 11 · 完整演练 —— 照抄一遍就通关

> **收尾篇**:把 [02]→[06] 串成**一次从零到能连的完整部署**,所有值写死、命令照抄。
> 每步先说**这步在干嘛**,给 ✅对了吗。纯文字。
>
> `[!]` 本篇用的 `example.com`、`203.0.113.x`、`198.51.100.x` 都是**文档保留地址**(照抄连不到任何东西),
> 表格里「你的情况」一列告诉你换成什么。你按自己的值替换即可。

---

## 0. 本次演练的设定

| 东西 | 本例的值 | 你的情况 |
|---|---|---|
| 域名 | `example.com` | 换成你买的 |
| 面板机 | Ubuntu,公网 IP `203.0.113.10` | 你的面板机 |
| 节点机 | Debian,公网 IP `198.51.100.20` | 你的节点机 |
| 用户面地址 | `https://app.example.com` | `app.<你的域名>` |
| **后台地址** | `https://k7m9x2.example.com` | **冷僻子域名**.你的域名(别用 admin/panel,见 [03](03-panel-deploy.md)) |
| 节点 ID | `3` | 建完节点面板会告诉你 |
| 节点 secret | `kJ8xQ2mN...`(32 位随机串) | 节点编辑页上能看到,**别外传** |
| 节点端口 | `39500` | 你自己定,记得防火墙放行 |

---

## 1. 占位符对照表(其余章节的 `<...>` 对应本篇哪个值)

| 占位符 | 意思 | 本例值 |
|---|---|---|
| `<面板地址>` | **用户面**完整 URL(带 https) | `https://app.example.com` |
| `<面板机>` | 面板机(SSH) | `root@203.0.113.10` |
| `<节点IP>` | 节点机 | `198.51.100.20` |
| `<节点ID>` | 节点数字 ID | `3` |
| `<secret>` | 节点通信密钥 | 节点编辑页那串 |
| `<端口>` | 节点监听端口 | `39500` |

`[!!]` 最易搞混 `<面板地址>`:是**用户面**(`app.`)不是后台(那个冷僻名)。填成后台的话节点每次请求都撞 Access 登录页。

---

## 2. 面板机上(起服务 + 初始化)

🎯 **这步在干嘛**:把面板跑起来、建第一个管理员。

```bash
ssh root@203.0.113.10
git clone <你拿到的仓库地址> 91vpn
cd 91vpn
cp .env.example .env
```
编辑 `.env` 改三行(token 第 3 步拿到再填):
```
APP_URL=https://app.example.com
ADMIN_HOST=k7m9x2.example.com
CLOUDFLARE_TUNNEL_TOKEN=
```
```bash
docker compose up -d
docker compose exec app composer install
docker compose exec db mysqladmin ping -proot            # 等它说 mysqld is alive
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=AdminSeeder --force
```
**✅ 对了吗**:`docker compose ps` 六个服务在(cloudflared 还在 restarting 正常,下一步给 token)。

---

## 3. Cloudflare 上(隧道 + 身份门)

🎯 **这步在干嘛**:让域名能访问、后台加 Access 身份门。详细界面步骤见 [03 §2](03-panel-deploy.md),本例填的值:

**两条 Published application routes**(**同一条隧道**):

| Subdomain | Domain | Type | URL |
|---|---|---|---|
| `k7m9x2` | `example.com` | HTTP | `http://web:8080` |
| `app` | `example.com` | HTTP | `http://web:80` |

**一个 Access 应用**:Application domain 填 `k7m9x2.example.com`,Path 留空,策略 Allow + 你的邮箱。
`[!!]` **别给 `app.example.com` 加 Access**(否则节点心跳 `/mod_mu` 和用户订阅 `/sub` 全被挡)。

拿到 token 回面板机:
```bash
nano .env                      # 填 CLOUDFLARE_TUNNEL_TOKEN
docker compose up -d cloudflared
```
**✅ 对了吗**:
```bash
curl -sI https://app.example.com/login        # 200
curl -sI https://app.example.com/admin        # 404(公网口不放后台)
curl -sI https://k7m9x2.example.com/          # 302 → cloudflareaccess.com
```

---

## 4. 发布 agent 二进制

🎯 **这步在干嘛**:让节点能从面板下载 agent。在有 sogacore 代码的机器上(可以就是面板机):
```bash
bash tools/publish-agent.sh /root/91vpn
```
**✅ 对了吗**:`curl -sI https://app.example.com/agent/v1/install.sh` 返 200。

---

## 5. 在面板里建节点

🎯 **这步在干嘛**:登记节点、拿到装节点要用的 ID 和 secret。

浏览器开 `https://k7m9x2.example.com`(先过 Cloudflare 邮箱验证)→ 用 `admin` / `password` 登录 → **立刻改密码**(右上角 🔑 图标 → 改密页)。

节点管理 → 添加节点:名称 `日本01`、角色 **落地**、地址 `198.51.100.20`、端口 `39500`、协议/传输 `VMess`/`TCP`、倍率 `1`、等级门槛 `0`。

保存 → 编辑,记下 **节点 ID = 3**、**secret = `kJ8xQ2mN...`**。
`[!]` 编辑页有条拼好的「用户名单接口」,粘进浏览器应返回 `{"ret":1,"data":[...]}`——**当场确认面板侧通了**。

---

## 6. 装节点

🎯 **这步在干嘛**:在节点机上把 agent 装起来。
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
**✅ 对了吗**:最后 `✅ 安装完成，节点已就绪`。

---

## 7. `[!!]` 放行端口(最容易漏)

🎯 **这步在干嘛**:装完 ≠ 能连,那个端口要在**两层**防火墙放行。
```bash
# 还在节点机上
ufw status && ufw allow 39500/tcp
```
云厂商控制台安全组也放行 `39500/tcp`。
**从你自己的电脑**验证:`nc -zv 198.51.100.20 39500`(连得上这层就通了)。

---

## 8. 验收

节点机上:`systemctl is-active agent`(active)、`curl -s 127.0.0.1:9090/ready`(`{"ready":true}`)。
面板后台 → 节点管理:`日本01` 显示**在线**。

---

## 9. 建用户、连上(终验)

后台 → 用户管理 → 新增,或前台 `https://app.example.com/register` 注册。
用该账号登录前台 →「我的节点」→ 复制订阅链接(形如 `https://app.example.com/sub/aBcD1234...`)→ 导入客户端 → 选 `日本01` → 连。
**✅ 成了** = 能上网。

---

## 通关了 🎉

到这你有了:能自助注册的用户面板、一个在线节点、一条能用的订阅、锁死的后台。接下来按需要:

| 想做什么 | 去哪 |
|---|---|
| 抗封锁(给落地上 REALITY) | [06](06-reality.md) |
| 加中转(用户连香港、落地在日本) | [05](05-relay.md) |
| 节点配置项详解 | [04](04-node-deploy.md) |
| 出问题了怎么查 | [09](09-ops.md) |
