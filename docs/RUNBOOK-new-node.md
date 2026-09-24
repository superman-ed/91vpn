# Runbook · 新节点上线

从「打算买一台机器」到「用户能连上」的完整顺序。**每一步都有闸门 —— 不过不要进下一步。**

参考细节不在这里重复，去看：
- `sogacore/docs/guide/04-node-deploy.md` —— `install.sh` 参数与 `agent.conf` 全部配置项
- `sogacore/docs/guide/06-reality.md` —— REALITY 原理与 dest 要求
- `sogacore/docs/guide/05-relay.md` —— 中转/落地分离（本 runbook 只覆盖**直连落地**）
- `docs/decisions/entry-dispatch.md`（D-5）—— 入口域名两层 CNAME
- `docs/decisions/domain-allocation.md`（D-7）—— 哪个域该承载什么

---

## 闸门 0 · 买之前（最省钱的一步）

`[!!]` **按这个顺序问，第 1 句没有明确答复就不要进第 2 句。** 一句话能否决的事，别花两轮去测延迟。

```
1. 独享【上行】带宽多少 Mbps？独享还是峰值/共享？
2. 流量额度是【雙向】还是【單向出站】？超出怎么收费？
3. 有没有 Looking Glass？没有能否代跑 traceroute 到我给的 6 个 IP？
4. IP 被墙能否免费更换？多久？一年几次？
5. 有没有退款期？（有退款期 = 可以先买后测晚高峰）
```

### 带宽的硬线

```
套餐承诺的最高限速   300 Mbps   (VIP③)
                     200 Mbps   (VIP②)
                     100 Mbps   (VIP① / 轻量)
```

`[!!]` 独享上行**低于 300 Mbps** 就撑不起 VIP③ 的承诺 —— 一个用户就能跑满。
要么买够，要么把套餐限速降到机器撑得住的数（见 `LAUNCH-CHECKLIST` L-21）。

`[!!]` 带宽和「三网优质」是**互斥**的 —— 优质线按 Mbps 卖。
**目标是"三网不烂"（<80ms）而不是"三网优质"（<20ms）**：
用户分辨得出卡顿，分辨不出 60ms 和 15ms。而我们卖的是
Netflix / YouTube / ChatGPT —— 那是吃带宽的。

### 三网延迟：六个探针 IP

```
219.141.136.12     北京电信      58.60.188.222    广州电信
202.106.50.1       北京联通      210.21.196.6     广州联通
211.136.112.200    北京移动      120.196.165.24   广州移动
```

用 itdog.cn/ping 打这六个点，**晚高峰 20:00–23:00 再测一轮**。

判读回程线路：`59.43.x`=CN2 · `202.97.x`=163 · `219.158.x`=169 ·
`223.120.x`=CMI · `221.183.x`=CMNET · **出现美国/日本城市 = 绕路，放弃**。

### 成本红线

```
成本 ≤ ¥0.045/GB 算舒服（售价约 ¥0.15/GB 的 30%）
雙向计费：额度 ÷ 2 才是可用的用户流量
不限流量 + 带宽数字 = 真容量；不限流量 + 无带宽数字 = 陷阱
```

**闸门：三网都 <80ms 且上行够，才付钱。** 有退款期的可以先买后测。

---

## 闸门 1 · 面板建节点

后台 → 节点管理 → 新增。

| 字段 | 填什么 |
|---|---|
| 名称 | **带地区关键词** —— 落地页的「通航地区」是从节点名里识别的（`HomeController::REGION_KEYWORDS`） |
| 地址 | 机器的公网 IP（入口域名稍后再挂，见闸门 4） |
| 端口 | 不要用 443/80，那两个端口的扫描密度最高 |
| 协议 | `vless` + REALITY（推荐）或 `vmess` |
| 角色 | `landing`（直连落地） |
| 等级门槛 | **`0` 才对新用户可见** —— 非 0 的话新注册用户订阅里看不到它 |
| 限速 | 按机器实际上行填，不要超过它 |
| 启用 | 先**不要**勾 —— 验收通过再开，避免把不通的节点发进订阅 |

`[!!]` **先不启用**这条是有代价换来的：2026-09-24 有 19 个占位节点因为
`enabled=1` 被发进了所有人的订阅，还把落地页吹到 13 个地区。

记下节点 ID 和 secret（详情页有「重置通信密钥」）。

---

## 闸门 2 · 部署 agent

### 前置：二进制得能被下载

```bash
cd /home/dev/web/sogacore/agent
CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -trimpath -ldflags "-s -w" \
  -o /tmp/agent-linux-amd64 ./cmd/agent
cp /tmp/agent-linux-amd64 ../deploy/install.sh /home/dev/web/91vpn/public/agent/v1/
```

`[!]` `public/agent/` 在 `.gitignore` 里 —— 二进制不进仓库，**换服务器要重新 build**
（见 `DEPLOYMENT.md §3b`）。

### 方式 A：一键部署（推荐）

节点行的「部署」按钮，填 SSH 主机/端口/用户 + 私钥正文或密码。
凭据只走 stdin，不落盘。

`[!]` 表单里的「面板地址」由 `NODE_API_URL` 预填（**不是** `APP_URL`）——
节点回连走不推广的低调域，而不是投广告的品牌域。理由见 `config/app.php`
的 `node_api_url` 说明。部署前扫一眼这个值对不对：

```bash
grep NODE_API_URL .env          # 应为 https://app.91app.shop
```

`[!]` 装完可在节点机上核对一次：

```bash
grep webapi_url /etc/agent/agent.conf
```

### 方式 B：手工安装

```bash
bash install.sh \
  --panel sspanel-uim \
  --api-url https://app.91app.shop \
  --node-id <ID> \
  --api-key-file /root/secret \
  --server-type vless
```

`[!!]` **`--server-type` 必须与面板里的协议一致。** 它取自**机器上的
`agent.conf`，不是 nodeInfo** —— 曾因此白查 14 分钟：面板改了协议，
节点还在跑旧协议，而两边都不报错。

`[!]` 一机只能跑一个 agent（`/etc/agent/agent.conf`、`agent.service`、
`127.0.0.1:9090` 全是写死的单实例路径）。装第二个会"报成功而什么都没做"。
`install.sh` 有护栏，且放在换二进制**之前**。

**闸门：面板节点详情页出现心跳。**

```bash
# 在面板机上查
docker compose exec -T app php -r '
require "/var/www/html/vendor/autoload.php";$a=require_once "/var/www/html/bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$n=App\Models\Node::find(<ID>);
printf("心跳 %s 秒前 · 自报协议 %s\n", time()-(int)$n->last_heartbeat, $n->reported_server_type ?: "-");'
```

`[!!]` 心跳字段叫 **`last_heartbeat`**（unix 秒）。不要写 `last_seen_at` ——
**`nodes` 表根本没有这一列**，而 Eloquent 对不存在的属性**静默返回 NULL**，
于是你会得到"从未上报"这个完全错误的结论（本 runbook 写作时就这么错过一次）。
也不要用 `updated_at`：面板写别的字段也会动它。

---

## 闸门 3 · REALITY dest（只有 vless+REALITY 需要）

在**节点机上**跑（不是面板机 —— 要测的是节点到候选站的路径）：

```bash
# 一次性装工具(面板 dest 候选区有现成可复制的这条)
curl -fsSL https://app.91app.shop/agent/v1/destprobe-linux-amd64 \
  -o /usr/local/bin/destprobe && chmod +x /usr/local/bin/destprobe

# 面板:节点详情 → dest 候选 → 生成清单,然后复制页面给出的命令。形状是:
destprobe -vps <本机公网IP> -d 30s -c 6 候选域名1 候选域名2 …
```

`[!]` 候选域名是**位置参数**,没有 `-candidates` 这种开关。
`-vps` 传本机 IP(用于判就近),`-d` 总预算,`-c` 并发。加 `-json` 出机读格式。

选 `PREFERRED` 的。然后在面板填：

- **REALITY dest** = `站点:443`
- **REALITY server_names** = 与 dest **同站**的域名（面板会校验同站，不同站直接拒绝）

`[!!]` 点「用最优」按钮会**同时**更新 dest 和 server_names。此前只更新 dest，
留下不匹配的 server_names —— agent 的 `ErrRealityIncomplete` 会拒整个节点。

`[!]` 勾了 REALITY 就**关掉 TLS** 那一项（两者是两种安全层，同开以 REALITY 为准）。

**闸门：从外部验伪装。** 正确 SNI 要拿到 dest 的真证书，错误 SNI 要与真站一致：

```bash
# 正确 SNI → 应当 Verify return code: 0 (ok),证书主体是 dest 那个站
openssl s_client -connect <节点IP>:<端口> -servername <dest域名> </dev/null 2>&1 | \
  grep -E 'subject=|Verify return code'

# 错误 SNI → 应当与真站【一致】,而不是报错或给出不同响应
openssl s_client -connect <节点IP>:<端口> -servername www.bing.com </dev/null 2>&1 | tail -3
```

---

## 闸门 4 · 入口域名（两层 CNAME）

`[!!]` **订阅里必须发域名而不是裸 IP** —— 裸 IP 被墙时只能改节点配置 +
重发订阅 + 等客户端更新（默认 24 小时）。挂了域名只需改一条 A 记录。

Cloudflare（**灰云，必须直连** —— 代理流量走不了隧道）：

```
标签   hkN.marveo.fun    A      <节点IP>     TTL 60    灰云
门牌   cdnN.marveo.fun   CNAME  hkN.marveo.fun        灰云
```

面板 → 入口域名 → 新增 `cdnN.marveo.fun`，绑到该节点，**手动点「设为在用」**
（新增后默认是"备用"，不点订阅不会发它）。

**闸门：**

```bash
dig +short cdnN.marveo.fun    # 应当先出 CNAME 再出 IP
```

---

## 闸门 5 · 启用并验收

回面板把节点的**启用**勾上，然后六项全过才算上线：

```bash
# ① 心跳(见闸门 2 的命令)
# ② 订阅里出现了,且发的是【域名】不是 IP
curl -s -A 'clash-verge/2.0' "https://sub.91app.shop/sub/<你的token>" | grep -A3 '<节点名>'
# ③ v2rayNG 格式也有
curl -s -A 'v2rayNG/1.8.0' "https://sub.91app.shop/sub/<token>" | base64 -d | grep -c '://'
# ④ 自检「可用节点」为 ok 且计数 +1
# ⑤ 用真客户端连上,看流量有没有记到面板
# ⑥ 三网延迟复测一次(机器装完可能和售前测的不一样)
```

**闸门 ⑤ 是唯一能证明端到端通的** —— 心跳只说明 agent 连得上面板，
不说明用户连得上节点。

---

## 不通怎么回滚

```bash
# 面板:取消「启用」 —— 立刻从所有人的订阅里消失(SubscriptionService 筛 enabled)
# 节点机:
systemctl stop agent && systemctl disable agent
journalctl -u agent -n 200 --no-pager     # 看失败原因
```

`[!]` 入口域名的 A 记录可以留着，改指向别的机器即可。

---

## 已知会踩的坑

| 坑 | 症状 | 出处 |
|---|---|---|
| `server_type` 只看机器上的 `agent.conf` | 面板改了协议，节点没变，两边都不报错 | 白查 14 分钟 |
| 一机两 agent | 安装**报成功而什么都没做**，新节点永远没心跳 | `install.sh` 已加护栏 |
| REALITY server_names 不跟 dest 变 | agent `ErrRealityIncomplete` 拒整个节点 | 面板已加同站校验 |
| `enabled=1` 但机器还没好 | 死节点进所有人订阅 | 19 个占位节点事件 |
| ~~一键部署的 `api_url` 指向牺牲域~~ | **已修**（2026-09-24）：改读 `NODE_API_URL`，不配才回落 `APP_URL` | 见闸门 2 |
| 用 `last_seen_at` 判在线 | **该列不存在**，而 Eloquent 对不存在的属性静默返回 NULL → 误判成"从未上报" | 本 runbook 写作时踩到 |
| 面板不可达时 fail-open 无上界 | agent 无限期沿用旧用户列表，**没有告警** | `LAUNCH-CHECKLIST` L-11 |
| dest 挂掉 | 全员断线，**没有任何人会被通知** | `LAUNCH-CHECKLIST` L-22 |
