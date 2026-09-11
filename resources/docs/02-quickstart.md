# 02 · 快速开始

目标：**一台面板 + 一个落地节点，客户端能连上**。约 30 分钟。

前置：一台装了 Docker 的机器（面板）、一台 Linux VPS（节点）、一个域名。

---

## 1. 起面板

```bash
cd 91vpn
cp .env.example .env          # 按需改 APP_URL / 数据库
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=AdminSeeder   # 建管理员
```

面板容器只绑回环（`127.0.0.1:8088`），**对外一律经 Cloudflare Tunnel** ——
配置见 [03](03-panel-deploy.md)。想先本地看看，`ssh -L 8088:127.0.0.1:8088 <server>`。

## 2. 建一个节点

后台 → 节点管理 → 添加节点：

| 字段 | 填什么 |
|---|---|
| 名称 | 随意，会显示在用户订阅里 |
| 角色 | **落地** |
| 地址 | 节点机的公网 IP 或域名 |
| 端口 | 客户端要连的端口，如 `443` |
| 协议 / 传输 | `vmess` / `tcp`（最简形态，先跑通再说） |
| 倍率 | `1` |
| 等级门槛 | `0`（所有用户可见） |

保存后在节点列表点「编辑」，页面上有该节点的**通信密钥**（secret）——
下一步要用，**不要贴进聊天或工单**。

## 3. 装节点

后台节点列表点「部署」，填 SSH 信息即可（见 [04](04-node-deploy.md)）。
想手工装：

```bash
curl -fsSL <你的面板地址>/agent/v1/install.sh -o /tmp/install.sh
bash /tmp/install.sh \
  --panel sspanel-uim \
  --api-url https://<你的面板域名> \
  --node-id <节点ID> \
  --api-key-file /path/to/secret \
  --server-type vmess
```

`[!]` 密钥走 `--api-key-file` 而不是 `--api-key`：后者会出现在进程命令行里，
本机任何用户 `ps` 一下就能看到。

装完自检：

```bash
systemctl status agent          # active
curl -s 127.0.0.1:9090/ready    # 200
journalctl -u agent -f          # 看它拉配置、建监听
```

## 4. 确认面板收到心跳

后台节点列表里那台应当显示**在线**，心跳在 60 秒内。

没有的话按这个顺序查（详见 [09](09-ops.md)）：

1. `journalctl -u agent -n 50` —— agent 有没有报错
2. 节点上 `curl -sI <面板地址>/mod_mu/nodes/<id>/info?key=<secret>` —— 通不通、返回什么
3. 面板侧 `docker compose logs web | grep mod_mu` —— 请求到没到

## 5. 建用户、拿订阅

后台 → 用户管理 → 新增；或直接在前台注册一个。
用户登录后在「我的节点」页拿到订阅链接，导入客户端即可。

---

## 下一步

- 想上 **REALITY**（抗封锁）→ [06](06-reality.md)
- 想加 **中转**（用户连香港、落地在日本）→ [05](05-relay.md)
- 上生产前请读 [03](03-panel-deploy.md) 的隧道与 Access 部分 ——
  面板直接裸奔在公网上是另一回事。
