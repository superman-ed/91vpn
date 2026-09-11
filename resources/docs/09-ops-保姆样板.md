# 09 ·(保姆版样板)运维手册 —— 出问题从这查

> **保姆版**:出问题时的"查故障"总入口。用法:**先在 §2 故障对照表按现象找"多半是什么",再按 §3 顺序动手查**。纯文字。
> 真实值:面板 `app.91app.shop`、后台 `summer.91app.shop`(`ADMIN_HOST`)。

---

## 1. 观测端点(先学会看节点"活没活、能不能服务")

🎯 **这步在干嘛**:每个节点在 `127.0.0.1:9090`(装机 `--api-addr` 可改)开了三个自检口。

| 端点 | 含义 |
|---|---|
| `/health` | 进程活着 |
| `/ready` | **可服务**——监听在、且(REALITY 节点)dest 可达 |
| `/metrics` | Prometheus 格式指标 |

`[!!]` `/health` 与 `/ready` 分开是有原因的:REALITY 的 dest 挂掉时端口照听、`/health` 照样 200,而**没人能握手**。**判断能不能用看 `/ready`,别只看在线绿点。**
`[!]` 刚启动头十几秒 `/ready` 返 **503 是正常的**(REALITY 要先探一次 dest);**持续** 503 才是故障。

**关键指标**(完整以 `agent/internal/metrics/names.go` 为准):

| 指标 | 看什么 |
|---|---|
| `agent_uptime_seconds` | 进程活了多久 |
| `user_count` / `traffic_bytes_total` | 服务的用户数 / 流量总量 |
| `core_listening` / `core_restart_total` | 内核在听吗、重启过几次 |
| `core_reality_dest_up` | REALITY 的 dest 可达吗 |
| `last_pull_timestamp_seconds` / `last_push_timestamp_seconds` | 上次拉配置 / 上次上报 |
| `report_queue_depth` / `report_retry_total` / `report_dropped_total` | 上报队列深度、重试、**丢弃** |
| `config_validation_failed_total` | 配置校验失败次数 |

`[!]` `report_queue_depth` 持续不降 = 面板一直失败、流量在堆;队列持久化(节点重启不丢),但 `report_dropped_total` 一涨就是真在丢账了,尽快修面板。
`[!]` `last_push_timestamp_seconds` 长时间不动而 `core_listening` 正常 = "节点在服务、但面板不知道",用户能连、账记不上。

---

## 2. `[!!]` 故障对照表(出问题先查这个)

🎯 **怎么用**:按"你看到的现象"找那一行,右列是**最常见的原因**,都是实际踩过的。

| 现象 | 多半是 |
|---|---|
| **节点全部同时失联** | 面板域名变了(临时隧道重启换随机域名),或 Access 套到了 `/mod_mu/*` 上 |
| **面板显示在线、`/ready` 正常,客户端却连不上** | 节点端口没在防火墙放行。`[!!]` 心跳是节点**往外发**的、不需入站放行 → 每项检查都绿。机器里查 `ufw status`,机器外查云厂商安全组 |
| 浏览器看面板正常,只有节点报 **403** | Cloudflare 机器人检测拦了 agent(UA 像浏览器但 TLS 指纹是 Go)——见 [03 §2.6](03-panel-deploy.md) |
| 面板显示节点在线,用户连不上 | REALITY 的 dest 挂了(端口在听、握手不成) |
| 客户端连上立刻断 | 开了 Mux/smux(xray 内核节点一律别开) |
| 经中转连不上、两端日志都正常 | PROXY 头配对错开(中转发头落地没收,或反过来)——看规则页校验 |
| UDP 服务"莫名不对" | 裸端口转发开了 `send_proxy_protocol`(现已主动关 UDP 并告警) |
| 防火墙"配好了"端口还开着 | 机器归 ufw 管,规则却追加在 INPUT 链尾 → 永远走不到 |
| **后台页面 500** | 看 `storage/logs/laravel.log`,多半是**某个类没随视图一起部署**(写操作才炸、只开列表页看不出来) |
| 后台进不去、返回 **404** | `ADMIN_HOST`(`summer.91app.shop`)与你访问的主机名不一致(这是设计:走错门就当不存在) |

---

## 3. 排障顺序(照这个动手)

🎯 **节点不上线**(每步都**在节点机上**跑):
```bash
journalctl -u agent -n 50 --no-pager                          # 1. agent 自己怎么说
curl -sI https://app.91app.shop/mod_mu/nodes/<id>/info?key=<secret>   # 2. 从节点能不能打通面板
ss -tlnp | grep agent                                          # 3. 端口在听吗
```
面板侧:`docker compose logs web | grep mod_mu`(请求到没到)。
`[!]` 第 2 步**必须在节点上**执行——从你开发机能通不代表节点能通(DNS/防火墙/机房出口都可能不同)。

🎯 **用户连不上某个节点**:
1. 面板节点列表:在线吗?dest 健康吗?
2. 订阅里那条的地址端口,和节点实际监听的对得上吗?
3. 从外部 `nc -z <地址> <端口>`——端口可达吗?
4. 客户端日志调到 `info` 看拒绝原因(默认 `warning` 会把原因藏起来)。

---

## 4. 日常维护

- **定时任务**由 `scheduler` 容器跑,`docker compose ps scheduler` 确认它活着;保留策略见 [03 §4](03-panel-deploy.md)。
- **备份**数据库与 `.env`。
- **发新版 agent 后**,重新发布 `public/agent/v1/`(换机器部署面板后也要重发)。

---

## 5. 安全要点(一张清单)

| 项 | 要求 |
|---|---|
| 面板端口 | 只绑回环,对外经隧道 |
| 后台 | Cloudflare Access + `ADMIN_HOST` **双层**(再加 nginx 公网口 /admin→404,共三层) |
| 节点 secret | 每节点一份随机串;泄露就在节点页重置(**重置后同步改节点配置**) |
| SSH 凭据 | 一次性、经 stdin、不落盘 |
| REALITY 私钥 | 只下发节点,不进订阅/不进日志(日志里是哈希指纹) |
| `accept_proxy` 的落地 | **必须**只对中转可达(PROXY 头无认证) |
| 审计日志 | 记变更内容但**脱敏**:字段名含 secret/key/password/token/cred/uuid/private 的只记"有/无" |

---

## 接下来

- 面板部署/隧道/保留策略 → [03](03-panel-deploy.md) · 中转排障 → [05](05-relay.md) · REALITY 排障 → [06](06-reality.md)
