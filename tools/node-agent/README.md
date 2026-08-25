# 91VPN 节点上报 agent

把节点服务器上代理内核统计的**每用户流量**，按面板 `mod_mu` 契约上报，让面板真正统计到服务器带宽消耗。

## 原理

```
面板  ──GET /mod_mu/users?node_id&key──▶  拿可服务用户名单(id/uuid)
内核  ──每用户 uplink/downlink 字节───▶  agent 读取(增量)
面板  ◀─POST /mod_mu/users/traffic────   {"data":[{user_id,u,d}]}
```

面板端(接收 + 结算 + 写 `node_daily_traffic`）已就绪且验证过。agent 是节点侧唯一需要部署的东西。

## 前置：内核统计约定

生成节点 inbound 配置时，把每个 vmess client 的 **email 设为用户 id**（纯数字），并开启 Xray Stats：

```jsonc
// xray inbound clients
{ "id": "<用户 uuid>", "email": "123" }   // 123 = 面板 user id

// xray 顶层
"stats": {},
"policy": { "levels": { "0": { "statsUserUplink": true, "statsUserDownlink": true } } },
"api": { "tag": "api", "services": ["StatsService"] },
// 再加一个 dokodemo-door inbound 监听 127.0.0.1:10085 tag=api 路由到 StatsService
```

这样内核统计项 `user>>>123>>>traffic>>>uplink|downlink` 能直接映射回面板 user_id。

## 运行

```bash
# 常驻，每 60s 上报一次（读后清零 = 增量）
python3 report_agent.py \
  --panel https://your-panel.com \
  --node-id 1 \
  --secret <节点密钥(面板节点管理里那串)> \
  --source xray --xray-api 127.0.0.1:10085 --interval 60

# 或环境变量
PANEL_URL=... NODE_ID=1 NODE_SECRET=... SOURCE=xray python3 report_agent.py
```

参数：`--source xray`（真实）| `mock`（联调，造随机流量验证链路）；`--dry-run` 只打印不 POST；`--interval 0` 只跑一次（配 crontab 用）。仅依赖 Python 3 标准库。

### crontab（每分钟）

```
* * * * * PANEL_URL=https://your-panel.com NODE_ID=1 NODE_SECRET=xxx SOURCE=xray /usr/bin/python3 /opt/agent/report_agent.py --interval 0 >> /var/log/vpn-agent.log 2>&1
```

### systemd（常驻）

```ini
[Unit]
Description=91VPN node report agent
After=network.target
[Service]
Environment=PANEL_URL=https://your-panel.com NODE_ID=1 NODE_SECRET=xxx SOURCE=xray
ExecStart=/usr/bin/python3 /opt/agent/report_agent.py --interval 60
Restart=always
[Install]
WantedBy=multi-user.target
```

## 联调验证（无需真内核）

对着面板用 mock 源打一次，确认 `node_daily_traffic` 增长即链路通：

```bash
python3 report_agent.py --panel http://127.0.0.1:8088 --node-id 1 --secret <密钥> --source mock --interval 0
# 面板回执 {"ret":1,"count":N} 即成功
```

## 口径说明

- 本 agent 上报的是**代理层流量**（内核按 uuid 统计的上下行）≈ 用户实际消耗，可拆分到用户/节点。
- 面板按节点倍率结算：`billed = 原始 × traffic_rate`；`node_daily_traffic` 同时存原始(u/d)与计费(billed)。
- 若还想要**网卡真实出口带宽**（含系统流量/协议开销，拆不到用户），可另加 vnstat 采集上报作成本对照——需面板加一个字段，本 agent 预留扩展点。

## 可靠性(不丢量)

内核用 `-reset` 读增量 = 读完即清零。若上报失败而增量已清零，就会永久丢数据。
agent 用 **backlog** 兜底：POST 失败的增量落地 `--backlog` 文件，下轮**合并重报**，
面板回执 `ret:1` 才清空。所以面板短暂抽风/网络抖动不丢量。
注意：拉不到用户名单时**整轮跳过、不读内核**（不触发清零），也不会丢。

## 多节点规模化(节点很多时)

架构本身水平扩展：每节点独立 agent + node_id/secret，面板按 node_id 隔离，
`TrafficService` 用事务 + 原子自增，多节点并发上报安全。上量前面板侧建议补：

- **批量写入**：`record()` 目前逐用户 UPDATE，几十节点×每分钟×数百用户时改批量/队列。
- **用户名单缓存**：`/mod_mu/users` 每节点每分钟拉全量，加 Redis 缓存 / etag 降压。
- **错峰**：多节点 crontab 加随机秒级抖动，避免整点同时打面板。

这套是 SSPanel-mod_mu / V2Board 的成熟节点模型，XrayR/soga 已在生产带上百节点验证。

## 待完善

- 在线 IP 上报（`/mod_mu/users/aliveip`，用于在线用户/多设备限制）：需从内核连接信息取 sourceIP，Xray 需解析 access log 或用 mihomo `/connections`。当前 agent 只做流量上报。
