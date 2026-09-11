# 12 · 实验环境：一台服务器 + 一个域名 + 一台 Windows

这一篇是**最小可用的完整链路**：从一台空服务器开始，做到 Windows 上连上、
测速、在面板里看到流量。每步都有"应该看到什么"。

`[!]` **哪些经过实测**：服务器侧的每条命令（含"同机走回环连面板"这条关键路径、
测速命令、回环拉 install.sh）都在真实环境跑过。
**Windows 侧与 Cloudflare 后台的点击流程没有实测** —— 前者我没有 Windows 环境，
后者要账号操作。那两段是按真实经验写的，但客户端版本更新时菜单名可能对不上，
按意思找。

和 [11 · 完整演练](11-worked-example.md) 的区别：那篇是两台机器（面板 + 节点）；
这篇**面板和节点在同一台**，更省钱，也是自己练手最常见的形态。

---

## 0. 你需要准备

| | 要求 | 大概花费 |
|---|---|---|
| 服务器 | 1 核 1G 起、Debian 12 或 Ubuntu 22.04、**境外**（境内做不了这件事） | 几美元/月 |
| 域名 | 任意后缀，便宜的就行 | 几美元/年 |
| Windows 电脑 | 你现在这台 | — |

`[!]` 服务器要境外的：节点是用户出网的那一跳，放境内没有意义。

本篇假设的值（你替换成自己的）：

| | 本例 |
|---|---|
| 服务器 IP | `198.51.100.20` |
| 域名 | `example.com` |
| 用户面 | `https://app.example.com` |
| 后台 | `https://admin.example.com` |
| 节点端口 | `39500` |

---

## 1. 连上服务器

Windows 10/11 自带 SSH，打开 **PowerShell**：

```powershell
ssh root@198.51.100.20
```

第一次会问 `Are you sure you want to continue connecting`，输 `yes`。
然后输密码（VPS 商家给你的）。

**应该看到**：命令提示符变成了服务器上的，比如 `root@vps:~#`。

---

## 2. 装 Docker

```bash
curl -fsSL https://get.docker.com | sh
docker --version && docker compose version
```

**应该看到**：两个版本号。

---

## 3. 起面板

```bash
git clone <你拿到的仓库地址> /root/91vpn
cd /root/91vpn
cp .env.example .env
nano .env
```

改这两行（`CLOUDFLARE_TUNNEL_TOKEN` 第 5 步再填）：

```
APP_URL=https://app.example.com
ADMIN_HOST=admin.example.com
```

`[!]` nano 的保存方式：`Ctrl+O` → 回车 → `Ctrl+X`。

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec db mysqladmin ping -proot
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=AdminSeeder --force
```

**应该看到**：最后一条输出 `INFO  Seeding database.`

```bash
curl -sI http://127.0.0.1:8088/login | head -1
```

**应该看到**：`HTTP/1.1 200 OK`

---

## 4. 域名托管到 Cloudflare

1. 注册 Cloudflare 账号（免费）
2. **Add a site** → 填 `example.com` → 选 **Free**
3. 它给你两个 NS 地址
4. 去**买域名的地方**（注册商）把 NS 改成这两个
5. 等生效，几分钟到几小时

在服务器上验证：

```bash
dig +short NS example.com
```

**应该看到**：Cloudflare 给的那两个地址。**没看到就别往下走**，后面每步都会失败。

---

## 5. 建隧道

详细点击流程见 [03 §2](03-panel-deploy.md)，这里只列本例要填的值。

Zero Trust（`one.dash.cloudflare.com`）→ Networks → Tunnels → Create a tunnel
→ Cloudflared → 起个名字。

复制出来的安装命令里 `eyJ` 开头那串就是 token，填进服务器的 `.env`：

```bash
nano /root/91vpn/.env      # CLOUDFLARE_TUNNEL_TOKEN=eyJ...
docker compose up -d cloudflared
docker compose logs cloudflared | tail -5
```

**应该看到**：`Registered tunnel connection`，且隧道在后台显示 **HEALTHY**。

进那条隧道 → **Published application routes** → 加两条：

| Subdomain | Domain | Type | URL |
|---|---|---|---|
| `admin` | `example.com` | HTTP | `http://web:8080` |
| `app` | `example.com` | HTTP | `http://web:80` |

Access（Zero Trust → Access → Applications → Self-hosted）：
Application domain 填 `admin.example.com`，Path **留空**，
策略 Allow + 你的邮箱。

`[!!]` **只给 `admin.` 建 Access，不要给 `app.` 建** —— 后者要服务用户和节点。

在 Windows 的 PowerShell 里验证：

```powershell
curl.exe -sI https://app.example.com/login
curl.exe -sI https://admin.example.com/
```

**应该看到**：第一条 `200`，第二条 `302`（跳 Cloudflare 登录）。

---

## 6. 进后台，建节点

浏览器打开 `https://admin.example.com` → 输邮箱收验证码 → 进登录页
→ 用 `admin` / `password` 登录 → **立刻改密码**（右上角 → 账号）。

节点管理 → 添加节点：

| 字段 | 填 |
|---|---|
| 名称 | `我的节点` |
| 角色 | 落地 |
| 地址 | `198.51.100.20` |
| 端口 | `39500` |
| 协议 / 传输 | `vmess` / `tcp` |
| 倍率 | `1` |
| 等级门槛 | `0` |

保存 → 点「编辑」，记下 **节点 ID**（比如 `1`）和 **通信密钥**。

---

## 7. `[!!]` 装 agent —— 同机要用回环地址

回到服务器：

```bash
cd /root/91vpn && bash /root/sogacore/tools/publish-agent.sh /root/91vpn
```

（sogacore 代码也在这台机器上的话。没有的话见 [04 §1](04-node-deploy.md)。）

```bash
umask 077 && printf '%s' '你的节点密钥' > /root/node.secret

curl -fsSL http://127.0.0.1:8088/agent/v1/install.sh -o /tmp/install.sh
bash /tmp/install.sh \
  --base-url http://127.0.0.1:8088/agent/v1 \
  --panel sspanel-uim \
  --api-url http://127.0.0.1:8088 \
  --node-id 1 \
  --api-key-file /root/node.secret \
  --server-type vmess
```

`[!!]` 注意 `--api-url` 填的是 **`http://127.0.0.1:8088`**，不是公网域名。
面板和节点在同一台机器上时，agent 直接走回环连面板：

- 不经 Cloudflare → **不会被机器人检测拦**
- 隧道挂了节点照常工作 → 只是你打不开后台
- 少一跳，更快

`[D]` 实测确认：回环这条路能完整调 `mod_mu`，而后台路径仍然 404
（回环打到的是公网口那个 server 块）。

`[!]` 用户订阅里的地址仍然来自 `APP_URL`（公网域名），不受影响。

**应该看到**：

```
✅ 安装完成，节点已就绪
```

```bash
systemctl is-active agent          # active
curl -s 127.0.0.1:9090/ready       # {"ready":true}
```

后台节点列表里那台应当显示**在线**。

---

## 8. 放行端口

```bash
ufw status
ufw allow 39500/tcp        # 如果 ufw 是 active
```

云厂商控制台的安全组也放行 `39500/tcp`。

在 **Windows PowerShell** 里验证：

```powershell
Test-NetConnection 198.51.100.20 -Port 39500
```

**应该看到**：`TcpTestSucceeded : True`

`[!!]` 这一步最容易漏，而且症状极具迷惑性：面板显示在线、`/ready` 正常，
**只有客户端连不上**。因为心跳是节点**往外发**的，不需要入站放行。

---

## 9. 建用户、拿订阅

后台 → 用户管理 → 新增一个（或去 `https://app.example.com/register` 自己注册）。

用那个账号登录 `https://app.example.com` → 「我的节点」→ 复制订阅链接，
形如：

```
https://app.example.com/sub/aBcD1234EfGh5678
```

---

## 10. Windows 客户端

推荐 **Clash Verge Rev**（开源、界面友好、内核就是我们测试用的 Mihomo）。
从它的 GitHub Releases 下 Windows 安装包。

`[!]` 各客户端版本的菜单名字略有出入，按意思找：

1. 打开后进「**订阅**」（Profiles）页
2. 把订阅链接粘进输入框 → 「导入」
3. 导入成功后能看到「我的节点」这一条
4. 进「**代理**」（Proxies）页，点选那个节点
5. 回首页，打开「**系统代理**」（System Proxy）开关

**应该看到**：节点列表里有你建的那个，延迟测试能出数字。

其它客户端也行：v2rayN（Windows 老牌）、Nekoray 等，都支持订阅链接导入。

---

## 11. 验证连上了

浏览器打开一个查 IP 的网站（比如 `ip.sb`）。

**应该看到**：显示的 IP 是 `198.51.100.20`（你的服务器），不是你家的宽带 IP。

看到自己家的 IP 说明**没走代理** —— 检查系统代理开关、检查规则模式
（有些客户端默认「规则」模式，国内网站直连、国外走代理，这时要打开国外网站测）。

---

## 12. 测速

**方法一 · 客户端自带**：Clash Verge Rev 的代理页上，每个节点右边有个
延迟测试按钮。数字是到节点的**延迟**（毫秒），不是带宽。

- 100ms 以内：很好
- 100–250ms：可用
- 300ms 以上或经常超时：线路不佳，考虑换机房或加中转（[05](05-relay.md)）

**方法二 · 实际带宽**：开着代理去 `speed.cloudflare.com` 或
`fast.com` 测。测的是"你 → 服务器 → 目标"整条链路，受服务器带宽、
你的宽带、线路质量三者共同限制。

`[!]` 便宜 VPS 常见 100Mbps 共享带宽，晚高峰能跑到 20–50Mbps 就算正常。

**方法三 · 服务器本身的上限**：

```bash
# 在服务器上，看它自己出网多快
curl -o /dev/null -w "%{speed_download}\n" https://speed.cloudflare.com/__down?bytes=50000000
```

结果单位是字节/秒，除以 125000 得到 Mbps。这是**上限** ——
你经过代理只可能比它慢。

---

## 13. 在面板里看流量

用了一会儿之后：

**用户视角**：`https://app.example.com` → 首页显示已用/剩余流量，
「流量明细」页有按天的曲线。

**管理视角**：后台 → 用户管理，每个用户一行显示已用流量；
后台 → 节点管理，每个节点显示「今日 / 累计」。

`[!]` 流量不是实时的：节点默认每 **60 秒**上报一次。刚下载完看不到数字很正常，
等一两分钟。

`[!]` 面板记的是**代理流量**（乘过倍率的算"计费流量"），
而机房账单算的是**整机网卡流量**，两者天然不同 ——
后者在节点的「整机额度」列。

---

## 14. 故障排查

按这个顺序，**每一条都在对应的机器上执行**。

### 客户端连不上

```powershell
# Windows 上:端口通不通
Test-NetConnection 198.51.100.20 -Port 39500
```

- `False` → 防火墙/安全组没放行（第 8 步）
- `True` 但仍连不上 → 往下

```bash
# 服务器上:节点自己怎么说
journalctl -u agent -n 50 --no-pager | grep -iE "error|reject|fail"
```

`[!!]` 拒绝的原因在**服务器**日志里，不在客户端日志里 ——
客户端只知道连接断了。实测过：客户端 info 级日志只有一行"匹配到了这个节点"。

### 面板显示节点离线

```bash
systemctl is-active agent                                    # 不是 active → 看下一条
journalctl -u agent -n 50 --no-pager
curl -sI "http://127.0.0.1:8088/mod_mu/nodes/1/info?key=你的密钥"
```

| 返回 | 意思 |
|---|---|
| `200` | 面板正常，问题在 agent |
| `401` | 密钥或节点 ID 不对 |
| 连不上 | 面板容器没起来：`docker compose ps` |

### 网页打不开 `app.example.com`

```bash
docker compose ps                      # 六个容器都在？
docker compose logs cloudflared | tail
dig +short app.example.com             # 解析出来了吗
```

- 解析不出来 → Cloudflare 的路由没建好（第 5 步）
- 解析出来但 `530` → 隧道没连上（`cloudflared` 容器状态）
- 跳到邮箱验证 → 你给 `app.` 也建了 Access 应用，删掉

### 连上了但很慢

1. 先看延迟（第 12 步）：延迟高是线路问题，加中转或换机房
2. 延迟正常但带宽低：服务器带宽被跑满，或晚高峰拥堵
3. 服务器上 `curl` 测它自己的出网速度 —— 如果它自己就慢，那是机房问题

### 流量对不上

- 等 60 秒（上报周期）
- 面板记代理流量、机房记整机流量，天然不同
- 倍率不是 1 的话，"计费流量" = 实际流量 × 倍率

---

## 做完之后

你现在有：一个能注册的用户面板、一个在线节点、Windows 上能用的连接。

接下来可选：

| 想做什么 | 去哪 |
|---|---|
| 抗封锁（REALITY） | [06](06-reality.md) |
| 加中转改善线路 | [05](05-relay.md) |
| 更多节点 | 重复第 6–8 步，`--node-id` 换成新的 |
| 出问题深入排查 | [09](09-ops.md) |
