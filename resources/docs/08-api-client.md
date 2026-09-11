# 08 · 客户端接口与订阅

## 1. 订阅

```
GET /sub/{token}
```

`token` 是用户的订阅令牌，在「我的节点」页可见、可重置。
面板按 User-Agent 与 `?flag=` 决定格式：

| 格式 | 说明 |
|---|---|
| Clash / Mihomo | YAML，含 `proxies` + 规则模板 |
| v2rayN / 通用 | base64 的 `vmess://` / `vless://` 链接列表 |

### `[!!]` 订阅里发的是"客户端要连的那一跳"

一个落地如果挂在中转后面，订阅里给的是**中转的地址 + 转发规则的监听端口**，
不是落地自身地址。每条可达路径一个条目，名字带入口后缀：

```
日本01            → 落地直连
日本01 · 香港中转  → 经香港中转
```

`accept_proxy` 的落地**不出直连条目** —— 那种端口上每个连接都必须带 PROXY 头，
直连客户端会被全部拒绝。

### 哪些节点会出现

必须同时满足：角色是 `landing`/`both`、在线、启用、等级 ≤ 用户等级。

`[!!]` 中转类角色**永远不出现在订阅、节点列表页和客户端 API 里** ——
它们的 port 恒为 0，用户连不上；而且中转的存在本身是内部拓扑。

### REALITY 条目

只带公开子集：`public-key` / `short-id` / `servername` / `flow` / `client-fingerprint`。
**私钥绝不进订阅。**

## 2. 客户端 API

前缀 `/api`。公开端点：`/auth/login` `/auth/register` `/plans` `/help`
`/app/version` `/app/config` `/crash`。

其余需要 `Authorization: Bearer <api_token>`：

| 分类 | 端点 |
|---|---|
| 账号 | `/user` `/account/password` `/account/profile` `/checkin` |
| 节点 | `/servers` `/node` `/node/reset-sub` `/node/reset-credential` |
| 流量 | `/traffic` `/subscribe-log` |
| 设备 | `/device/report` `/devices` |
| 商店 | `/orders` `/order/create` `/order/{id}/pay` `/wallet` `/wallet/recharge` |
| 消息 | `/announcements` `/messages` `/messages/read-all` |
| 工单 | `/tickets` `/tickets/{id}/reply` `/tickets/{id}/close` |
| 邀请 | `/invite` |

响应信封：`{"ret": 1, "data": ...}`。

`[!]` `/servers` 返回的是**展示用**列表（含 `locked` 标记表示超出用户等级），
它同样不含中转节点。

## 3. 给客户端开发者的注意事项

**订阅要定期刷新**。节点地址、REALITY 密钥、中转入口都可能变；
密钥轮换后旧订阅立刻失效。

`[!!]` **不要开 Mux / smux**。对接 xray 内核的节点一律不支持：
Mihomo 的 smux 是 sing-mux，xray 服务端只认自己的 mux.cool，两者不是一个协议；
而 xray 自己的 mux.cool 与 vision 流控互斥。症状是**连接建立后立刻断**。

`[!]` UDP 在 vision 节点上可用（走 XUDP），单包上限在 8 KiB 附近 ——
DNS、游戏心跳、QUIC 都在安全区。

`[!]` 调试连不上时把客户端日志调到 `info`：很多客户端默认 `warning`，
而拒绝的真正原因（flow 不匹配、short-id 不对）是 `[Info]` 级别。
