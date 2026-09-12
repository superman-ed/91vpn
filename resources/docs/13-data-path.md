# 13 · 数据通路（完整版）

一条用户流量从客户端到互联网，中间每一跳做了什么。
**按代码核实**，不是示意图 —— 每处标注都能在源码里找到对应。

前置：不确定 REALITY / vision / XUDP / PROXY protocol 是什么，
先看 [00 · 术语表](00-glossary.md)。

---

## 1. TCP：最常见的那条路

```
┌──────────┐
│  客户端   │  vless + REALITY + vision
└────┬─────┘
     │ ① TCP：ClientHello，SNI = serverNames 之一
     ▼
┌─────────────────────────────────────────────┐
│ 中转节点  dokodemo-door（InDirect，不解协议） │
│                                             │
│   ② 按转发规则选路                            │
│      roundrobin / random / leastconn / leastload
│      主池全死 → 备池（backup_balance）        │
│                                             │
│   ③ freedom 出站                             │
│      DestinationOverride → 落地的 addr:port  │
│      ProxyProtocol = 0（关）/ 1 / 2           │
└────┬────────────────────────────────────────┘
     │ ④ TCP：[PROXY 头][客户端原始字节…]
     │    ⚠ 开了 send_proxy_protocol 时【不是原样转发】
     ▼
┌─────────────────────────────────────────────┐
│ 落地节点  Xray VLESS + REALITY Server        │
│                                             │
│   ⑤ sockopt.acceptProxyProtocol 消费 PROXY 头 │
│      ↓                                      │
│   ⑥ REALITY Server 启动                      │
│      ├──→ 【先连 target】                     │
│      │     ClientHello 转给真站              │
│      └──← ServerHello / Certificate 由真站返回 │
│      ↓                                      │
│   ⑦ REALITY 鉴权（X25519 / AEAD）            │
│      ├─ 通过 ──→ ⑧ vless 用户认证             │
│      └─ 未通过 ─→ 继续按 target 机制处理       │
│                  （字节在两侧对拷，客户端看到  │
│                   的自始至终是真站的响应）     │
│   ⑨ 用户流量 → freedom 出站                  │
└────┬────────────────────────────────────────┘
     │
     ▼
   互联网
```

### `[!!]` ⑥ target 不是"鉴权失败后的兜底"

这是最容易建立错误模型的一处。直觉上会以为：**先验密钥，不对才去连 dest**。
实际相反 —— `[S]` 源码 `xtls/reality` 的 `tls.go:162`，`Server()` 的第一件事
就是连 target：

```go
func Server(ctx, conn, config) (*Conn, error) {
    remoteAddr := conn.RemoteAddr().String()
    target, err := config.DialContext(ctx, config.Type, config.Dest)  // ← 第一行实质动作
    if err != nil {
        conn.Close()
        return nil, errors.New("REALITY: failed to dial dest: " + err.Error())
    }
    ...
    hs := serverHandshakeStateTLS13{c: &Conn{conn: &MirrorConn{Conn: conn, Target: target}}}
```

**在读 ClientHello 之前、在任何鉴权之前。** 之后握手全程由 `MirrorConn`
夹在中间：ClientHello 发往真站，ServerHello 与证书由真站返回，
REALITY 的 X25519/AEAD 鉴权是在这之后才发生的。

`[!!]` **实际后果：dest 连不上时，每条新连接在第一步就 `return err`。**
不是"鉴权失败后兜底失败"，而是**握手根本没机会开始** —— 所以它是全量的、
立即的、不分用户的（密钥正确的老用户一样连不上）。

按错误模型推会得出相反的预期："至少密钥对的人还能连"。**不会。**

而已建立的连接不受影响（它们不再走握手），于是现象是：
**老连接好好的，新连接全断，端口还在听，`/health` 还是 200。**
这正是诊断页要单独查 dest 的原因。

### `[!]` ① SNI 填的是 serverNames，不是 dest

`Dest` 与 `ServerNames` 在配置里是**两个独立字段**
（`[S]` 服务端用 `config.ServerNames[hs.clientHello.serverName]` 单独校验）。

通常两者一致（`dest = www.example.com:443`、`serverNames = [www.example.com]`），
但那是**惯例不是等式**：能用哪些 SNI 取决于 target 接受什么、证书 SAN 覆盖什么。
所以不要把 `target = xxx.com:443` 机械等同于 `SNI = xxx.com`。

### `[!!]` ④ 不是"原样转发"

开了 `send_proxy_protocol` 时，中转在**每条连接最前面加一段 PROXY 头**
（v2 是 28 字节），落地靠 `acceptProxyProtocol` 消费掉。准确的说法是
**L4 透明转发 + 前置 PROXY protocol 元数据头**。

两件事必须成对，而**配错时两端都不报错**：

| | 结果 |
|---|---|
| 中转发头 + 落地收头 | ✅ |
| 中转发头 + 落地不收 | ❌ 落地把 PROXY 头当成 TLS 握手数据 → 失败 |
| 中转不发 + 落地要收 | ❌ 落地等一个永远不来的头 → 超时 |

`[!]` `freedom` 的 `proxyProtocol` 取值是 **1 或 2**，不填即 0（关闭）。

`[!!]` 由此推出一个常被误判的结论：**开了收头的落地，那个端口不能直连**。
直连客户端没有 PROXY 头，会被全部拒绝 —— 所以诊断页对这类节点把
"从面板连不上"判成**正常**，能连上反而是 warn（说明防火墙没锁）。

`[!]` 顺带一提：REALITY 服务端自己也能向 target 发 PROXY 头
（`config.Xver`，`[S]` `tls.go:174`）。那是**另一层**，与中转发给落地的
那一层无关 —— 我们没有用它。

---

## 2. UDP：`[!!]` 它根本没到中转那一层

游戏、QUIC、DNS 这些 UDP 流量走的是**同一条 TCP 连接**：

```
┌──────────┐
│  客户端   │
└────┬─────┘
     │ UDP 数据包
     ▼
  ┌────────────────┐
  │ vision / XUDP  │  把 UDP 包封进 TCP 流
  └────┬───────────┘
       │
       │  ═══════ 从这里往下，链路上只有 TCP ═══════
       ▼
  中转 dokodemo ──→ 落地 REALITY Server
       │                  │
       │                  ▼
       │            ┌────────────┐
       │            │ 拆 XUDP 封装 │
       │            └────┬───────┘
       │                 │ UDP 数据包
       ▼                 ▼
   （只见 TCP）        互联网
```

`[D]` 实测三格全通（`compatibility/udp-through-relay.md`）：直连落地、
经中转不发头、经中转发头+落地收头。

**所以"UDP 能不能穿中转"这个问法本身就偏了** —— 对 vless+vision 的用户流量
而言，中转那一跳**没有 UDP**。

`[S]` 单包上限约 **8 KiB**（xray 的 `common/buf.Size = 8192`）。
DNS、游戏心跳、QUIC（受 MTU 约束通常 ≤1500）都在安全区。

### `[!!]` 另一条完全不同的 UDP 路径

上面说的是**用户流量**。还有一种：转发规则的入站类型是 `direct`（裸端口转发），
转的是一个**真正的 UDP 服务**：

```
UDP 客户端 ──→ 中转 dokodemo（tcp,udp）──→ UDP 服务
```

`[D]` 这条路上开 `send_proxy_protocol` 会**毁掉 UDP**：xray 把 PROXY 头当成
**一个独立的数据报**先发出去，载荷在下一个包里 —— 接收端的整条 UDP 流
**错位一个包**：

```
第 1 个包 → 28 字节纯 PROXY 头，没有载荷
第 2 个包 → 你以为是第 1 个包的内容
```

而落地的 `acceptProxyProtocol` 是个 **TCP sockopt**，UDP 路径上没有它 ——
**两端"正确配对"也剥不掉**。

处置：agent 主动把 UDP 从这条规则上摘掉并告警，面板在保存规则时提前提示。
**UDP 不通是能被发现的故障，静默错位不是。**

---

## 3. 哪一跳知道"你是谁"

| 跳 | 看得见用户身份吗 | 为什么 |
|---|---|---|
| 客户端 | ✓ | 它就是用户 |
| **中转** | **✗** | L4 透传，不解协议。用户的加密在落地才打开 |
| 落地 | ✓ | vless 认证在这里做 |

`[!!]` 这是 **D-1** 的物理基础，不只是一条约定：中转**没有能力**认出用户，
所以给它用户名单毫无用处，而泄露风险是实打实的（中转往往是租来的、
最容易被接管的那一台）。

面板在读写两个方向都有守卫：`/mod_mu/users` 对中转返回空，
`/mod_mu/users/traffic` 与 `/users/aliveip` 对中转直接拒绝。

**PROXY protocol 是这条边界上唯一的例外**：它让落地知道**来源 IP**，
但仍然不知道**是哪个用户** —— 用户身份还是靠 vless 认证。

---

## 4. 计量在哪一跳发生

| 数据 | 在哪算 | 用途 |
|---|---|---|
| 按用户流量 | 落地（认证之后才知道归谁） | 计费 |
| 按规则流量 | 中转（`rule_traffic`，只有规则没有用户） | 归因"哪条中转吃了多少" |
| 整机网卡流量 | 节点自己（`node_net_traffic`） | 对机房账单 |
| 在线 IP | 落地（用户级）/ 中转（只有 IP） | 设备数限制 / 观测 |

`[!]` 中转的**下行**是在连接结束时一次性结算的（走 splice，内核搬数据，
只能在结束时问一次总数）。长连接的下行会滞后 —— 这份数据用于**归因**，
不能当实时用量看。

---

## 5. 订阅里给用户的是哪一跳

`[!!]` 是**中转的地址 + 转发规则的监听端口**，不是落地自己的地址。

面板从转发规则自动推导：每条可达路径出一个订阅条目，
名字带入口后缀（「日本01 · 香港中转」）。`accept_proxy` 的落地
**不发直连条目** —— 那种端口上直连必然被拒（见 §1 的 ④）。

详见 [05 · 中转架构](05-relay.md) §6。
