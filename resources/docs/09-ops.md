# 09 · 运维手册

## 1. 观测端点

节点上监听 `127.0.0.1:9090`（装机时 `install.sh --api-addr` 可改）：

| 端点 | 含义 |
|---|---|
| `/health` | 进程活着 |
| `/ready` | **可服务** —— 监听在、且（REALITY 节点）dest 可达 |
| `/metrics` | Prometheus 格式 |

`[!!]` `/health` 与 `/ready` 分开是有原因的：REALITY 的 dest 挂掉时，
端口照常监听、`/health` 照常 200，而**没有任何客户端能完成握手**。
编排系统要看 `/ready`。

`[!]` 刚启动的头十几秒 `/ready` 返回 **503 是正常的** —— REALITY 节点要先
探一次 dest 才算可服务。持续 503 才是故障。健康检查的宽限期要给够，
否则编排系统会在它刚起来时就把它杀掉重启，陷入循环。

关键指标：

| 指标 | 看什么 |
|---|---|
| `agent_uptime_seconds` | 进程活了多久 |
| `user_count` | 当前服务的用户数 |
| `traffic_bytes_total` | 流量总量 |
| `core_listening` / `core_restart_total` | 内核在听吗、重启过几次 |
| `core_reality_dest_up` | REALITY 的 dest 可达吗 |
| `last_pull_timestamp_seconds` / `last_push_timestamp_seconds` | 上次拉配置 / 上次上报是什么时候 |
| `panel_request_total` / `panel_request_error_total` | 打面板的次数与失败数 |
| `report_queue_depth` / `report_retry_total` / `report_dropped_total` | 上报队列深度、重试数、**丢弃数** |
| `report_success_total` | 上报成功数 |
| `config_validation_failed_total` | 配置校验失败次数 |
| `audit_violations_dropped_total` | 审计命中丢弃数 |

（完整清单以 `agent/internal/metrics/names.go` 为准。）

`[!]` `report_queue_depth` 持续不降 = 面板那边一直失败，流量在堆积。
队列是持久化的，节点重启不丢，但要尽快修面板 —— `report_dropped_total`
一旦开始涨，就是真的在丢账了。

`[!]` `last_push_timestamp_seconds` 长时间不动而 `core_listening` 正常，
说明"节点在服务、但面板不知道" —— 用户能连，账记不上。

## 2. `[!!]` 故障对照表

按"现象 → 多半是什么"排，都是实际踩过的：

| 现象 | 多半是 |
|---|---|
| **节点全部同时失联** | 面板域名变了（quick tunnel 重启会换随机域名），或 Access 套到了 `/mod_mu/*` 上 |
| **面板显示在线、`/ready` 正常，但客户端连不上** | 节点端口没在防火墙放行。`[!!]` 心跳是节点【往外发】的，不需要入站放行 —— 所以每一项检查都是绿的。机器里查 `ufw status`，机器外查云厂商安全组 |
| 浏览器看面板一切正常，只有节点报错 403 | Cloudflare 机器人检测拦了 agent（UA 伪装成浏览器但 TLS 指纹是 Go） |
| 面板显示节点在线，用户却连不上 | REALITY 的 dest 挂了（端口在听、握手不成） |
| 客户端连上立刻断 | 开了 Mux/smux |
| 经中转连不上，两端日志都正常 | PROXY 头配对错开 —— 中转发头而落地没收，或反过来 |
| UDP 服务"莫名其妙不对" | 裸端口转发开了 `send_proxy_protocol`（现已主动关 UDP 并告警） |
| 防火墙"配好了"但端口还开着 | 机器归 ufw 管，而规则追加在 INPUT 链尾 —— 永远走不到 |
| 后台页面 500 | 看 `storage/logs/laravel.log`，多半是某个类没随视图一起部署 |
| 后台进不去、返回 404 | `ADMIN_HOST` 与你访问的主机名不一致（这是设计：走错门就当不存在） |

## 3. 排障顺序

**节点不上线**：

```bash
journalctl -u agent -n 50 --no-pager        # 1. agent 自己怎么说
curl -sI <面板>/mod_mu/nodes/<id>/info?key=<secret>   # 2. 从节点上能不能打通面板
ss -tlnp | grep agent                        # 3. 端口在听吗
```

面板侧：`docker compose logs web | grep mod_mu` —— 请求到没到。

`[!]` 第 2 步要**在节点上**执行。从你的开发机能通不代表节点能通
（DNS、防火墙、机房出口都可能不同）。

**用户连不上某个节点**：

1. 面板节点列表：在线吗？dest 健康吗？
2. 订阅里那条的地址端口，与节点实际监听的对得上吗
3. 从外部 `nc -z <地址> <端口>` —— 端口可达吗
4. 客户端日志调到 `info` 看拒绝原因

## 4. 日常维护

- **定时任务**由 `scheduler` 容器跑，`docker compose ps scheduler` 确认它活着
- **保留策略**见 [03](03-panel-deploy.md) §4
- **备份**数据库与 `.env`
- **一键部署的产物**（`public/agent/v1/`）在发新版 agent 后要更新

## 5. 安全要点

| 项 | 要求 |
|---|---|
| 面板端口 | 只绑回环，对外经隧道 |
| 后台 | Access + `ADMIN_HOST` 双层 |
| 节点 secret | 每节点一份随机串；泄露了就在节点页重置（重置后要同步改节点配置） |
| SSH 凭据 | 一次性，经 stdin，不落盘 |
| REALITY 私钥 | 只下发给节点，不进订阅、不进日志（日志里是哈希指纹） |
| `accept_proxy` 的落地 | **必须**只对中转可达 —— PROXY 头无认证 |
| 审计日志 | 记变更内容但**脱敏**：字段名含 secret/key/password/token/cred/uuid/private 的一律只记"有/无" |
