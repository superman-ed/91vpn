# 07 · 节点接口（节点 ↔ 面板）

契约基线是 **sspanel-uim 的 mod_mu**。本项目在不破坏该契约的前提下做了扩展，
扩展一律放在 `custom_config` 里（见 `docs/decisions/ADR-007`）。

## 0. 鉴权

两种方式，面板都认：

```
GET /mod_mu/nodes/12/info?key=<节点 secret>     # 旧式,兼容 soga/XrayR
GET /mod_mu/nodes/12/info                       # 推荐
  X-Node-Secret: <节点 secret>
```

`[!]` 认不出时统一返回 **401** + `{"ret":0,"msg":"unauthorized"}`。
"节点不存在"与"密钥不对"**返回同一个东西**，所以探测者问不出某个 node_id
存不存在。密钥比较用 `hash_equals`，避免按字节计时猜解。

`[!]` `?key=` 仍然被接受（兼容 soga / XrayR），但面板每次都会记一条告警日志
提示切到请求头 —— query 会进 access log 和浏览器历史。

响应统一信封：`{"ret": 1, "data": ...}`。

## 1. 节点配置

```
GET /mod_mu/nodes/{id}/info
```

```jsonc
{
  "ret": 1,
  "data": {
    "node_id": 12,
    "name": "日本01",
    "server": "...",              // sspanel 的组合串,兼容老客户端
    "role": "landing",            // [!!] 本项目扩展:landing/relay/springboard/front/both
    "host": "", "port": 443,
    "type": "vless", "net": "tcp", "path": "",
    "tls": true,
    "security": "reality",        // 扁平字段,给 XrayR 之类兜底
    "flow": "xtls-rprx-vision",   // 同上
    "traffic_rate": 1.0,
    "node_class": 0,
    "node_speedlimit": 0,
    "node_group": 0,
    "custom_config": { ... },     // [!!] 权威通道,见下
    "base_config": { "pull_interval": 60, "push_interval": 60 }
  }
}
```

`[!!]` `role` 是**本项目的扩展**，sspanel 没有这个字段。中转节点靠它被识别；
拿不到 `role` 的实现会把中转当成落地，于是要求它有端口 —— 而中转的端口恒为 0。

### `custom_config`

REALITY / flow / security / accept_proxy 的**权威来源**。顶层那几个扁平字段
只是给不认识 `custom_config` 的实现兜底。

| 键 | 类型 | 说明 |
|---|---|---|
| `security` | string | `none` / `tls` / `reality` |
| `flow` | string | `xtls-rprx-vision` |
| `private_key` | string | REALITY 私钥 —— **只下发给节点** |
| `dest` | string | `host:port` |
| `server_names` | string[] | |
| `short_ids` | string[] | |
| `accept_proxy` | bool | 入站是否收 PROXY 头 |
| `dest_scan` | object | dest 候选筛查任务：`{id, candidates[]}` |

## 2. 用户名单

```
GET /mod_mu/users?node_id={id}
```

```jsonc
{ "ret": 1, "data": [ { "id": 101, "uuid": "...", "speed_limit": 0, ... } ] }
```

`[!!]` 中转角色拿到的是**空数组**（D-1）。不是错误，是设计。

`[!]` 只输出 `data` 一个键。曾同时输出 `data` 与 `users` 两个键以防消费端读哪个，
实测双键会把 payload 整整翻一倍 —— 一万用户时单次响应从 1.2 MB 变 2.3 MB，
而节点每 30–60 秒拉一次。

## 3. 上报

| 端点 | 方法 | 说明 |
|---|---|---|
| `/mod_mu/nodes/{id}/info` | POST | 心跳与状态（见下） |
| `/mod_mu/users/traffic` | POST | 按用户流量 `[{user_id, u, d}]` |
| `/mod_mu/users/aliveip` | POST | 在线 IP |
| `/mod_mu/users/detectlog` | POST | 审计命中 |

`[!!]` 后三个对**中转角色一律拒绝**：中转不认证用户，它上报的按用户数据
只可能是伪造的。

### 心跳 body

sspanel 契约的部分：`uptime` / `load` / `online_user` …

本项目的扩展：

| 字段 | 说明 |
|---|---|
| `accept_proxy` | 节点**实际在跑**的收头姿态（用于与面板配置比对） |
| `reality_dest` / `reality_dest_up` / `reality_dest_failures` | dest 健康 |

`[!]` `accept_proxy` 是**实测回报**而不是回显配置 —— 面板据此发现
"配了但没生效"。

## 4. 审计规则

```
GET /mod_mu/func/detect_rules      # 返回 [] 表示不审计
```

## 5. 中转专用

| 端点 | 方法 | 说明 |
|---|---|---|
| `/mod_mu/nodes/{id}/routes` | GET | 该中转要执行的转发规则（编译后的 JSON） |
| `/mod_mu/nodes/{id}/rules/traffic` | POST | 按规则的流量（不是按用户） |
| `/mod_mu/nodes/{id}/rules/aliveip` | POST | 按规则的来源 IP（只有 IP，没有 user_id） |
| `/mod_mu/nodes/{id}/rules/status` | POST | 出站健康状态 |
| `/mod_mu/nodes/{id}/rules/sync` | POST | 规则指纹回报（面板据此显示"节点同步到哪一版"） |

## 6. dest 筛查

```
POST /mod_mu/nodes/{id}/dest_scan
```

节点跑完一轮候选筛查后回报。`[!]` 面板会忽略**过期的 scan_id**
（运维已经改了候选清单，旧结果就不该覆盖新任务）。

## 7. 兼容性要点

- 响应必须能被 **gzip** —— agent 会发 `Accept-Encoding: gzip`
  （`[!!]` 这里踩过：面板不压时曾整个解不出来）
- `ret` 非 1 视为失败；agent 会把失败的上报**入持久化队列**稍后补报
- 拉取失败**不中断服务**：节点继续用上一份配置与用户名单
