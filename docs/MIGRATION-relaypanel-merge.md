# 迁移:中转拓扑并回 91vpn（relaypanel 退休）

> 目标：把中转（relay）拓扑管理从独立的 relaypanel **并回 91vpn**，让 91vpn 成为
> 中转+落地的**唯一节点控制面**（一张节点表、一个 mod_mu、一颗一键部署按钮）。
> 完成后 relaypanel 退休。
>
> **状态**：📋 已规划，未开工。逐步勾选，跨会话可接着做。
> 相关记忆：`relaypanel-merge-plan`、`node-oneclick-deploy`、`reality-relay-topology`。

## 背景（为什么这么做、为什么是这个方向）

- 91vpn **曾有**转发表，在 `2026_09_07_120000_drop_forward_tables.php` 删掉、外迁给
  relaypanel。那条迁移注释写明外迁动机：**只有一处能改中转配置**（避免两处都能改、
  只有一处会下发）。本次合并不是推翻该意图，而是把**单一管理点从 relaypanel 挪回 91vpn**。
- 为什么并进 91vpn 而非全放 relaypanel：91vpn 本就公网 + 客户端面向 + 持用户，加中转路由
  **不新增暴露面**；全放 relaypanel 则会**逼私有面板(127-only)上公网**、把用户库/支付拖进
  运维+部署面板、还要把大系统重写进小系统。三样都更差。
- 共同代价（合并本身固有）：用户数据 + 全网 SSH 部署能力最终同处一盒。**缓解**见 P0
  的 admin 网络限制。对单人自用、admin 127-only 的场景可接受。

## 已有 vs 要搬（开工前摸清的实况）

| | 91vpn 现状 | relaypanel（搬回来） |
|---|---|---|
| 节点表 | ✅ 权威（role/secret/reality/accept_proxy/reported） | 自己一张（role/secret/quota/心跳/rule_sync） |
| mod_mu | ✅ 落地侧（users/nodeInfo/心跳/dest_scan），`node.secret` | 中转侧 **routes** + rules/{traffic,aliveip,status,sync} |
| 编译链 | ❌ 已删 | ✅ ForwardRuleService + RuleCheck + RuleSync |
| Reality / AuditLog | ✅ 都有（复用） | 有（重复，丢弃 relaypanel 的） |
| 一键部署 | ❌ | ✅ DeployController/Deployer/DeployRun |
| 拓扑 UI | ❌ | ✅ rules / monitor / online-ip 视图 |
| 跨面板管道 | Internal/RelayController（accept-proxy 端点） | LandingPosture + 配对校验的 HTTP 半 |

**丢弃不搬**：relaypanel 的 Admin/Auth/Account/Dashboard/layout（91vpn 有自己的一套）。

---

## P0 预备（不改码）

- [ ] 盘 relaypanel 数据量：空/开发数据 还是 有真节点+真规则？（决定 P6 的 ID 重映射量）
  - 记结论：______________________
- [ ] 定 admin 网络限制方案：**推荐**给 91vpn 后台单开一个只绑 `127.0.0.1` 的 admin vhost，
      走 `ssh -L` 进（把 relaypanel 的 127-only 保护搬到 91vpn admin 上）。
- [ ] 冻结 relaypanel 的功能改动（迁移期间只读心态）。

## P1 数据模型搬回 91vpn（纯新增，drop 即回滚）

- [ ] 重建 `forward_rules` + `forward_outbounds`（用 relaypanel **演进版** schema：
      含 inbound_opts / reality / send_proxy 等字段）。
- [ ] 新增 `rule_traffic` / `rule_alive_ip` / `rule_outbound_status` / `node_net_traffic`。
- [ ] 给 91vpn `nodes` 加中转列：`quota_gb` / `quota_reset_day` / rule_sync 字段 / uptime·load。
- [ ] 建对应 Model（ForwardRule / ForwardOutbound / 三张状态表 / 扩展 Node）；Reality、AuditLog 复用 91vpn 的。
- ✅ 可逆：全是新表新列，无人引用，drop 回滚。

## P2 搬编译与校验服务（不接线）

- [ ] 搬 `ForwardRuleService`（编译链）、`RuleCheck`、`RuleSync` 进 91vpn。
- [ ] 移植 relaypanel 的测试（CompileParity、配对校验→本地版）。
- ✅ 可逆：纯新增，未接线不生效。

## P3 给 91vpn mod_mu 加中转端点（可并行验证）

- [ ] 现有 `mod_mu` 组加：`nodes/{node}/routes`、`nodes/{node}/rules/{traffic,aliveip,status,sync}`，
      复用 `node.secret` 中间件。
- [ ] 把**一台**中转 agent 的 `api_url` 重指 91vpn，验证能拉到 routes。
- 💡 白赚：91vpn 是公网的，中转终于能直接够到面板（relaypanel 127-only 时够不着）。
- ✅ 可逆：删路由即可；其余中转仍连 relaypanel。

## P4 拓扑管理 UI 进 91vpn 后台（可并行）

- [ ] 搬 `RuleController` + rules 视图 + monitor + online-ip。
- [ ] 把 relaypanel node-form 的额外字段（quota 等）并进 91vpn `AdminNodeController` / 节点表单。
- [ ] 挂在 91vpn admin 鉴权 + P0 网络限制之后。
- ✅ 可逆：新增后台页，cutover 前 relaypanel 仍权威。

## P5 一键部署进 91vpn（可并行）

- [ ] 搬 `DeployController` / `Deployer` / `DeployRun` 命令 + `deploy_runs` 表 + 按钮/弹窗。
- [ ] 落地部署简化：面板即 91vpn，node-id/secret/api-url **自动带入**，不再手粘。
- [ ] 部署路由走 P0 网络限制。
- ✅ 可逆：新增，未挂菜单前不影响。

## P6 数据切换（⚠️ 不可逆线）

- [ ] 若有真数据：导入 relaypanel 的节点+规则到 91vpn，建 **旧→新 ID 映射**，
      按映射改写规则里的 `inbound_node_set` / `target_node_set`（node id 数组）。
- [ ] 逐台把中转 agent `api_url` 重指 91vpn + 换统一 secret（部署按钮重装或手改）。
- [ ] 验证：每台中转从 91vpn 拉到 routes；落地不受影响。
- ⚠️ 过了这步就开始依赖 91vpn；保留 relaypanel **只读**当后路，别急着 P8。

## P7 删跨面板管道

- [ ] 删 `config/services.php` 的 `relay_internal_token`。
- [ ] 删 91vpn `app/Http/Controllers/Api/Internal/RelayController.php` + 其路由。
- [ ] 删 relaypanel `LandingPosture`；accept_proxy 配对校验从 HTTP → **本地查询**
      （RuleCheck 直接读同库的 `nodes.accept_proxy_protocol` vs `reported_accept_proxy`）。

## P8 relaypanel 退休（最后，且 91vpn 验稳后）

- [ ] 停 relaypanel 容器；归档仓库；留一份 DB dump 保底。
- [ ] cutover 后先让 relaypanel 只读跑一阵，确认无回退需求再真正下线。

---

## 主要风险/坑

- 🔴 **ID 重映射（P6）**：规则里 node-id 是 JSON 数组，逐一改；漏一个规则就静默指错落地。
- 🔴 **只留一份编译实现**：搬完**务必删 relaypanel 的 ForwardRuleService**，否则又变两份、
  回到 drop 迁移当初怕的"两处不一致"。
- 🟠 **admin 网络限制是部署活**（nginx/docker vhost），不是代码，别漏。
- 🟠 Audit 动作名两边口径统一。
- 🟠 relaypanel 无 git remote，退休前的归档需用户建仓/打包。

## 安全姿势

P1–P5 全程 relaypanel 照跑，91vpn 并行搭好、逐台验；真正切流量在 P6，且保留 relaypanel
只读后路。不一次掀桌。
