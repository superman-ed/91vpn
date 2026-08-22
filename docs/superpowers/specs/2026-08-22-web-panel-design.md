# 91VPN 第一阶段设计：Web 面板

> 版本：v1 · 日期：2026-08-22
> 目标：复刻 socloud.me（机场式 VPN 产品）的 Web 面板，用于学习。
> 最高原则：**复刻真站功能**（UI 不追求还原，功能等价即可）。
> 一手证据与逆向细节见 `/home/dev/web/91vpn/refs/`（README.md 为总索引）。

---

## 1. 背景与范围

### 1.1 对标产品
socloud.me = **SSPanel-UIM 旧版 + Malio 主题** 的机场面板。无营销官网，登录页即门面。
- 翻墙协议：**100% VMess**（双份订阅一手确认）
- 节点架构：中转入口 `cp.paeadiy.com` + 多端口区分落地（IEPL 专线中转，落地 IP 不暴露）
- 客户端：集成 Mihomo 内核（本阶段不涉及）
- 计费：订阅制，四维 = 流量 × 时长 × 限速 × 设备数

### 1.2 本阶段范围（主线优先）
聚焦跑通主线：**注册 → 买套餐 → 拿订阅链接 → 导入客户端 → 连上网能用**。

| 入口 | 本阶段 | 说明 |
|---|---|---|
| ① 用户前台 | ✅ 做 | 注册/登录/仪表盘/商店/节点设置 |
| ② 管理后台 | ⏸ 稍后 | 第一版用命令行/seeder/直插库代替 |
| ③ 订阅下发 `/sub/{token}` | ✅ 做 | 生成 Clash 配置 |
| ④ 节点 WebAPI `/mod_mu/*` | ✅ 做 | 对接 XrayR/V2bX |
| ⑤ 客户端 API | ⏸ 阶段二 | 做客户端时再说 |

### 1.3 非目标
- 不做管理后台 UI（本阶段）
- 不接真实支付网关（用模拟支付，接口预留）
- 不做工单/优惠券/审计/发票（后续按需）
- 不自研协议/内核（用现成 Mihomo + XrayR）

---

## 2. 技术栈

| 层 | 技术 | 理由 |
|---|---|---|
| 框架 | **Laravel 11**（PHP 8.3） | 现代 PHP，安全默认值最好，机场生态主流 |
| 前端 | **Blade + Tailwind CSS** | 服务端渲染，最省事（UI 无所谓） |
| 数据库 | **MySQL 8** | 与真站一致 |
| 缓存/队列 | **Redis** | 会话/缓存/队列(邮件、流量重置) |
| API 认证 | **Laravel Sanctum**（token） | 客户端/API 用；对应 user.api_token |
| 环境 | **Docker Compose** | php-fpm + nginx + mysql + redis 一键起 |

**安全基线**（从地基做起，规避 SSPanel 的坑）：
1. 密码 argon2id（非 md5）
2. CSRF 全站开启（Laravel 默认）
3. 节点一密钥一 secret（非公开默认值）
4. 支付回调验签
5. 订阅 token 随机可重置
6. API 限流；管理后台后续加 2FA

---

## 3. 架构

```
┌─────────────────────── Laravel 单体应用 ───────────────────────┐
│  Web(Blade)          Console(命令行)         API                 │
│  ├ 用户前台           ├ 建节点/套餐(代管理后台)  ├ /sub/{token}     │
│  │  认证/仪表盘/商店   └ 流量重置调度            │  订阅下发         │
│  │  节点设置                                    └ /mod_mu/*        │
│  └────────┬───────────────────────────────────────┬─ 节点WebAPI  │
│           ▼                                         ▼             │
│      Service 层（计费/订阅生成/流量结算/发货）                     │
│           ▼                                                       │
│      Eloquent Models ──→ MySQL + Redis                           │
└──────────┬──────────────────────────────────┬───────────────────┘
     /sub 下发订阅                        /mod_mu 拉用户+收流量
           ▼                                    ▼
    [Clash/Mihomo 客户端]              [节点后端 XrayR/V2bX + VPS]
```

**分层原则**：控制器薄（只管 HTTP），业务逻辑进 Service 层（计费、订阅生成、流量结算独立可测），模型只管数据。

---

## 4. 数据库设计

第一版 8 张核心表。字段基于 SSPanel 真实结构，Laravel 化命名，流量用 bigint 存字节。

### 4.1 `users`
| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| email | string unique | 登录邮箱 |
| password | string | argon2id |
| uuid | uuid | 连接用（VMess id） |
| passwd | string | 连接密码（改则同时换 uuid） |
| u / d | bigint | 已用上传/下载(字节) |
| transfer_enable | bigint | 流量配额(字节) |
| transfer_today | bigint | 今日已用 |
| class | tinyint | 等级 0=普通,1/2/3=VIP①②③ |
| class_expire | datetime | 等级到期 |
| node_speed_limit | int | 限速 Mbps，0=不限 |
| node_ip_limit | int | 设备数上限 |
| money | decimal(12,2) | 余额 |
| ref_by | bigint null | 邀请人 id |
| invite_token | string unique | 订阅链接 token（可重置） |
| api_token | string | 客户端 API token |
| is_admin | bool | |
| banned | bool | |
| timestamps | | |

### 4.2 `plans`（套餐）
`id, name(VIP①), price, period(month/quarter/year/half_year), transfer_gb(给多少流量), class(对应等级), speed_limit, ip_limit, duration_days(时长天数), sort, on_sale, stock(-1无限)`

### 4.3 `orders`（订单）
`id, user_id, plan_id, amount, status(pending/paid/cancelled), period, pay_method(balance/mock), paid_at, timestamps`

### 4.4 `nodes`（节点，支持中转架构）
| 字段 | 说明 |
|---|---|
| id, name | |
| server | 连接地址（中转入口域名，如 cp.paeadiy.com） |
| port | 端口（多端口区分落地） |
| type | 协议：vmess（第一版） |
| net | 传输：tcp/ws |
| traffic_rate | 流量倍率 |
| node_class | 等级门槛（class>=此值可连） |
| node_group | 分组 |
| speed_limit | 节点限速 |
| secret | 节点通信密钥（一节点一个） |
| online | 在线状态 |
| last_heartbeat | 最后心跳 |
| sort | 排序 |
| custom_config | json（备用） |

### 4.5 `balance_logs`（余额流水）
`id, user_id, amount, type(recharge/consume), balance_after, remark, timestamps`

### 4.6 其它
- `invite_codes`：`id, code, user_id(创建者), used_by null, timestamps`
- `announcements`：`id, title, content, sort, published, timestamps`
- `traffic_logs`（第一版简化，可只在 users 存总量；如做：`id, user_id, node_id, u, d, rate, logged_at`）

### 4.7 关系
```
users 1─* orders *─1 plans        用户下单买套餐
users 1─* balance_logs             余额变动
users.ref_by ─> users              邀请关系
nodes 独立（按 class/group 匹配 users 决定谁能连）
```

---

## 5. 核心模块设计

### 5.1 M1 认证
- **注册**：邮箱 + 邮箱验证码(第一版打到日志) + 昵称 + 邀请码(选填) + 密码 + 算术验证码。注册时生成 uuid/passwd/invite_token/api_token，初始 class=0、transfer_enable=0。
- **登录/登出**：Laravel session。
- **找回密码**：邮件重置链接(第一版日志)。

### 5.2 M2 用户中心
- **仪表盘**：流量卡片(已用/剩余/配额)、到期时间、等级、余额；每日签到。
- **节点设置**：显示订阅链接、重置订阅 token、重置连接密码(连带换 uuid)。
- **个人资料 / 改密码 / 公告展示**。

### 5.3 M3 商店与计费（模拟支付）
- **套餐列表**：VIP①②③ + 周期选择，价格实时算。
- **下单流程**：选套餐 → 创建 order(pending) → **"模拟付款成功"按钮** → 触发发货。
- **发货逻辑（计费核心，Service 层）**：
  ```
  transfer_enable += plan.transfer_gb (转字节)
  class = plan.class
  class_expire = max(now, 当前class_expire) + plan.duration_days
  node_speed_limit = plan.speed_limit
  node_ip_limit = plan.ip_limit
  order.status = paid
  ```
- **余额钱包**：充值(模拟)、余额流水、余额支付订单。

### 5.4 M4 订阅下发 `/sub/{token}`
- token → 找到 user → 校验未过期/未封禁 → 按 `class >= node_class` 筛节点 → 生成配置。
- **实现方式**：配置模板 + 动态注入
  - 静态模板：`proxy-groups`(Proxy/国际流媒体/亚洲流媒体/OpenAI/Steam/国内/其他) + `rules`(约700条，存 `resources/clash/rules.yaml`)
  - 动态部分：`proxies`(从筛出的 nodes 生成 vmess 条目) + 各 group 的节点名列表
- **第一版格式**：Clash YAML（最常用）。v2rayN(base64)、sing-box 等后续加。
- **节点条目模板**（真实样板）：
  ```yaml
  {name, type: vmess, server, port, uuid: <user.uuid>, alterId: 0, cipher: auto, udp: true}
  ```

### 5.5 M5 节点 WebAPI `/mod_mu/*`（兼容 XrayR/V2bX）
用 `nodes.secret` 鉴权（query param `key` 或 header）。
- `GET /mod_mu/users?node_id=X`：返回该节点可服务的用户名单（uuid/限速/限IP），过滤条件 `banned=0 AND class_expire>now AND class>=node.node_class AND (node_group匹配) AND transfer未超`。
- `POST /mod_mu/users/traffic?node_id=X`：收 `[{user_id,u,d}]`，`u×rate`/`d×rate` 累加到 user，超 transfer_enable 后下次拉取即排除。
- `POST /mod_mu/users/aliveip`：收在线 IP，配合 node_ip_limit 限设备(第一版可先记录不强制)。
- `GET /mod_mu/func/ping`：心跳，更新 node.last_heartbeat/online。

> 注：真站用旧版 `/mod_mu/`；新版 XrayR 也支持。接口以 SSPanel WebAPI 契约为准，具体字段实现时对照 ss-old 源码 `src/Controllers/Node/`。

---

## 6. 开发顺序与里程碑

每个里程碑有可验证产出。建议从 M0+M1 起步。

| MS | 内容 | 验证 |
|---|---|---|
| **M0** 脚手架 | Laravel+Docker起；Sanctum/argon2/CSRF配好；8表migration+模型+关系；seeder(套餐/测试节点/管理员) | `docker compose up` 起，库有表有数据，首页不报错 |
| **M1** 认证 | 注册(邮箱码+邀请码+算术码)/登录/登出；注册生成全套token | 能注册登录登出，库里用户带uuid/token |
| **M2** 用户中心 | 仪表盘/节点设置(订阅链接+重置)/资料/公告 | 登录见仪表盘和订阅链接，能重置 |
| **M3** 商店计费 | 套餐列表/下单/模拟付款/发货/钱包 | 买套餐→模拟付款→仪表盘见流量+等级+到期变化 |
| **M4** 订阅+WebAPI | /sub/{token}出Clash配置；/mod_mu拉用户+收流量结算 | 订阅导入Clash见节点；节点能拉到用户 |
| **M5** 端到端 | 装XrayR/V2bX(VPS/本地)接面板，走完整主线 | 🎯 注册→买套餐→订阅→Clash连上→能上网+流量计费 |

---

## 7. 目录结构（Laravel 约定）

```
app/
├─ Http/Controllers/    Auth/ User/ Api/(Sub, ModMu)
├─ Models/              User Plan Order Node BalanceLog ...
├─ Services/            BillingService(发货) SubscriptionService(生成配置)
│                       TrafficService(结算) NodeUserService(拉名单)
├─ Console/Commands/    CreateNode CreatePlan ResetTraffic
database/migrations/    8张表
resources/
├─ views/               Blade(前台)
└─ clash/rules.yaml     订阅规则模板
routes/
├─ web.php              前台
└─ api.php              /sub /mod_mu
docker-compose.yml
```

---

## 8. 风险与开放问题

- **模拟支付→真支付**：发货逻辑与支付解耦(Service层)，接真网关时只加回调，不改发货。
- **节点端验证依赖外部**：M5 需 VPS+XrayR，本地开发可先用假数据跑通 M4，M5 延后到有服务器时。
- **中转架构**：nodes 表已支持 server+port 模式；第一版可先用单节点(直连自己的VPS)验证，中转是运营优化。
- **traffic_logs 详细度**：第一版简化(只存总量)，图表化后续加。

---

## 9. 参考

- 逆向情报：`refs/README.md` 及 5 份拆解文档
- SSPanel 源码对照：`scratchpad/ss-old/`（2022.12，路由与 socloud 一致）
- 订阅样本分析：`refs/socloud/subscription/README.md`
- 安全基线：memory `sspanel-security-notes`
