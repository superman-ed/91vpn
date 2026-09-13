# 管理后台现状盘点（2026-09-13）

**方法**：全部取自代码与库，不是回忆。每一条都能追到路由、模型或表列。
盘点的目的只有一个 —— **不要重新设计一套已经存在 70% 的东西**。

口径：`✅ 已有` / `🟡 有一半` / `❌ 没有`。

---

## 一、总量

```text
管理路由    91 条，覆盖 20 个前缀
模型        34 个
后台视图    20 个目录
可配置项    settings 表 22 条
权限        is_admin 一个布尔        ← 全部问题里最要紧的一个
```

---

## 二、按提议的 5 大块对照

### ① 运营中心

| 项 | 状态 | 证据 |
|---|---|---|
| 数据总览 | ✅ | `admin` → `DashboardController`：今日新增/订单/收入、活跃、在线、待支付、待处理工单、收入趋势图、区间统计、返佣、净利 |
| 用户管理 | ✅ | `admin/users` 9 条路由 |
| 会员/套餐 | ✅ | `admin/plans` 8 条，含 `toggle-sale` / `move`（排序） |
| 订单 | 🟡 | `admin/orders` 4 条：列表、导出、取消、标记已付。**无退款** |
| 财务 | 🟡 | `admin/finance` + 导出。**无对账、无渠道维度** |
| 优惠活动 | ✅ | `admin/coupons` 7 条（含批量生成）、`admin/promo` 5 条（推广渠道）、`admin/rebates`（返佣） |
| 公告/内容 | 🟡 | `admin/announcements` 6 条、`admin/help` 6 条。**无 Banner、无下载页配置** |

`[!]` **Dashboard 的排序已经是"运营在上"**：第一屏是用户总数 / 累计收入 /
纯毛利 / 已支付订单，节点在第 5 格。提议里担心的"CPU、Relay 数量占首屏"
并没有发生。

### ② 用户中心

| 项 | 状态 | 证据 |
|---|---|---|
| 用户列表 | ✅ | `admin/users` |
| 用户详情 | ✅ | `admin/users/{user}` |
| 订阅状态 | ✅ | `SubscribeLog` 模型 + `admin/system/devices` |
| 使用情况 | ✅ | `DailyTraffic` / `NodeDailyTraffic` / `AliveIp` / `Device` |
| 封禁/解封 | ✅ | `users/{user}/toggle-ban` |
| 操作记录 | ✅ | `AuditLog` + `admin/system/audit` |
| 其它动作 | ✅ | `grant`（赠送）/ `reset-password` / `reset-traffic` |
| 注册来源 | ✅ | `PromoChannel` + `CaptureUtm` 中间件 + `admin/system/acquisition` |

`[!]` 这一块**基本齐全**。提议里列的字段几乎都有对应实现。

### ③ 节点与网络

| 项 | 状态 | 证据 |
|---|---|---|
| Relay / Landing | ✅ | `Node.role` + `admin/nodes` 11 条 |
| 健康检查 | ✅ | 分层健康态（`LayerHealth`：中转 / 到落地 / dest）+ `nodes/{node}/diagnose` |
| 配置 Desired/Applied/Hash | ✅ | `Node.applied_hash` / `fetched_hash` / `sync_error` + `admin/rules` |
| 故障事件 | 🟡 | `NodeHealthSpell` 刚落地（2026-09-13），**只有采集，没有页面** |
| 存活统计 | 🟡 | 同上，数据在跑，**刻意还没做展示** |
| 拓扑 | ❌ | 只有向导里的线性示意，**没有拓扑视图** |

### ④ 产品配置

| 项 | 状态 | 证据 |
|---|---|---|
| 套餐 | ✅ | `plans` 表：name / price / period / transfer_gb / reset_type / is_data_pack / **class（节点组）** / speed_limit / **ip_limit（并发）** / duration_days / sort / on_sale / stock |
| 节点分组 | ✅ | `Node.node_class` ↔ `Plan.class` |
| 订阅配置 | ✅ | `resources/clash/rules.yaml` 模板 + `SubscriptionService` |
| 公告 | ✅ | `admin/announcements` |
| 全局配置 | 🟡 | `admin/settings` 22 项 + 邮件/网关自测 |
| 客户端下载 | ❌ | **硬编码在 `User/DownloadController` 里，四个平台的 `url` 全是 `null`** |
| 套餐原价 | ❌ | 表里没有 `original_price`（做划线价要加） |

### ⑤ 系统管理

| 项 | 状态 | 证据 |
|---|---|---|
| 管理员 | ✅ | `admin/admins` 4 条 |
| 操作日志 | ✅ | `AuditLog` + `admin/system/audit` |
| 系统设置 | ✅ | `admin/settings` |
| 运行状况 | ✅ | `admin/system/health` / `crashes` / `emails` / `login-logs` |
| 构建版本 | 🟡 | agent 侧有（`buildinfo`，2026-09-13 起注入 git 版本），**面板自身没有** |
| 角色/权限 | ❌ | **只有 `is_admin` 布尔**（`AdminOnly` 中间件一行判定） |

---

## 三、真正的缺口（按影响排序）

1. **❌ 角色与权限**。`is_admin` 一个布尔。
   `[!!]` 提议里"运营人员看不到 Relay 细节"这件事，**完全依赖角色存在**——
   所以它不是 P1 的一项，是整个分层设想的**前提**。
2. **❌ 退款**。全库搜不到 refund 相关实现。订单只有
   `pending / paid / cancelled` 三态。
3. **🟡 发货维度**。没有独立的 `fulfillment_status`，但有
   `paid_at` / `activate_at` / `delivered_at` 三个时间戳 ——
   "钱扣了没发货"**查得出来**（`status=paid AND delivered_at IS NULL`），
   只是没有人把它摆到页面上当告警。
4. **❌ 客户端下载配置**。硬编码且 url 全空。
5. **❌ Banner / 活动页 CMS**。公告与 FAQ 有，Banner 没有。
6. **❌ 拓扑视图**。
7. **🟡 故障事件与存活统计的展示**。数据已在采集，展示是**有意推迟**的
   （样本不足时出图等于鼓励错误结论）。
8. **❌ 财务对账与渠道维度**。
9. **❌ 套餐原价**。

---

## 四、盘点得出的三条判断

**一、这不是"重新规划"，是"补权限 + 补几个洞 + 重组导航"。**
运营侧的实现覆盖率比提议假设的高得多：用户中心几乎齐全，套餐已全可配，
Dashboard 已经是运营指标在上。真正从零开始的只有权限、退款、Banner、
下载页配置、拓扑。

**二、提议的 ① 与 ② 有重叠。**
①「运营中心」里列了"用户管理"，②整块又是"用户中心"。
导航上同一个东西出现在两处，人会不知道该点哪个 —— 建议用户只留一处。

**三、`[!]` "运营和运维两条线并行"这条，成本要说清楚。**
并行的前提是有两拨人。当前是一个人 + 我，并行意味着来回切上下文 ——
而这一轮已经反复证明：**上下文切换正是漏判的来源**
（ROUND 判据 79 / 95：反复"失败"的验证先怀疑验证本身；
`assertRedirect()` 不证明目的地存在）。建议串行推进，但**按运营优先排序**。

---

## 五、建议的下一步（供讨论，不是结论）

```text
第 0 步   角色与权限          —— 它是"运营看不到运维细节"的前提，绕不过去
第 1 步   订单退款 + 发货告警  —— 钱相关，且已有 70% 的数据基础
第 2 步   下载页 + Banner 配置 —— 纯 CMS，改文案不该找开发
第 3 步   导航重组             —— 五大块，用户只出现一处
第 4 步   拓扑视图
第 5 步   存活统计展示         —— 等样本，不等代码
```

`[!]` 第 0 步之前不建议动导航：**导航的分层是权限分层的表现，
没有角色的话，"运营看到什么"只能靠自觉。**
