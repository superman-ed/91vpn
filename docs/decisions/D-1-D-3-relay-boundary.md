# D-1 / D-2 / D-3 —— 中转与落地之间的信任边界

> 这三条此前**只活在代码注释里**（`[decided]` 标记，散在 4 个文件 6 处），
> `docs/decisions/` 目录并不存在。本文把它们成文，内容照着代码注释写，不是事后复述。
>
> **这三条已封存。** 不因对其它服务的逆向结果而反过来修改 ——
> 外部研究是参考架构，不是推翻自己设计的理由。

---

## D-1 · 中转不认证用户

> 中转 / 跳板 / 入口一律不认证、不持有用户名单，只透传字节；**认证只在落地做**。
> —— `ModMu/UserController.php:18`

**读方向**：`/mod_mu/users` 不向 `role=relay` 的节点下发用户名单。

`[!!]` 这条判据曾经**定义了但从没被调用** —— `Node::needsUsers()` 存在、全项目零引用。
也就是说给节点标了 `role=relay`，照样把真实用户名单连同凭据发过去，
而**中转往往是租来的、最容易被接管的那一台**。
现象上还看不出异常：中转本来就不认证用户，多一份名单它也用不着。

**写方向**：中转上报的按用户流量、按用户在线 IP 一律不采纳。

- 流量：中转没有「这些字节属于谁」这个信息，它报的按用户流量只可能是伪造的。
  `[!!]` 这是**计费面** —— 一台被接管的中转若能替任意用户记流量，
  可以把别人的额度刷爆，或给自己的账号免单。中转的用量走**按规则**的上报。
- 在线 IP：中转没有用户身份，报上来的 `(user, ip)` 只能是编的。
  采纳它等于让被接管的中转能把任意用户挤下线、或污染审计记录。

**端点不返回 404 而返回空名单**：agent 在通用流程里会调它，
返回空是「我这里没有用户」的正确表达，让它拿 404 走错误路径是另一回事。

---

## D-2 · `inbound_cred` / `out_cred` 是节点间凭据，不是用户凭据

> 这是面板铸造的**节点间凭据**，不是用户凭据 —— 中转不认证用户（D-1）。
> —— `ForwardRuleService.php:139`

跨节点引用时，出站凭据 = 被引用规则的入站凭据，**编译期注入**（同文件 `:274`）。

因此这两者**允许**出现在 `compileForNode()` 的输出里。
把它们当成「用户凭据」而收紧，是对 D-2 的误读。

### 附带的既有约束

| | |
|---|---|
| **入站** REALITY `private_key` | **允许**。中转要伪装成 TLS 站点就必须终结 TLS，终结 TLS 就必须持有私钥。这是物理约束，不是设计缺陷 |
| **出站** REALITY `private_key` | **禁止**。拨号方只需 `public_key` / `short_id` / `server_names`。`ForwardRuleService` 在编译期主动 `unset`，防的是管理员误填 |

`[!]` 传输层参数（`ws` / `grpc` / `reality` 的 dest、server_names）**不能**塞进 `credential`：
那一列在 agent 侧是凭据（uuid/password/cipher/username），多出来的键要么被忽略、
要么被当成配置错误；语义上也不对 —— 它们不是秘密，混在一起会让「凭据轮换」
顺手把传输参数也换掉。

---

## D-3 · 节点配置的边界不变量（机械校验）

`compileForNode()` 的输出**不得**携带用户维度状态，**不得**携带出站 REALITY 私钥。

由 `tests/Feature/NodeConfigBoundaryTest.php` 机械保证，三个维度：

| 维度 | 判据 |
|---|---|
| **路径** | `private_key` 只允许匹配 `^rules\[\d+\]\.inbound\.reality\.private_key$` |
| **字段名** | 禁用名单从 `users` 表**实际列**推导（`Schema::getColumnListing`），不手写 —— 将来新增用户字段自动纳入 |
| **取值** | 取库里真实用户的 `uuid`/`passwd`/`email`/`api_token`/`invite_token`/`ref_code` 当靶子，逐个叶子比对 |

`[!!]` **第三个维度是关键。** `credential.uuid` 这个**键**是合法的，
但它的**值**绝不能是某个用户的 uuid。
只查键名会完全漏掉「把 User.uuid 赋给节点凭据」这类改动 ——
真正危险的不是 `key = uuid`，是 `node credential.uuid = USER.uuid`。
换句话说，测的是**数据的语义来源不得跨越 Node / User 边界**，
而不是粗暴禁止某个字段名。

同时有**正向断言**钉住允许项（`inbound_cred`、入站 REALITY 私钥、出站 `public_key`），
防止将来有人「顺手收紧」把它们一起禁掉。

### 反证

三类泄漏都注入验证过，测试必须变红：

```
不剥离出站私钥            → rules[0].outbounds[0].reality.private_key
用户 uuid 塞进节点间凭据   → rules[0].inbound.credential.uuid 的值等于 用户#1.uuid
transfer_enable 塞进规则   → 出现用户维度字段：rules[0].transfer_enable
```

另有一条测试钉住摊平函数本身，否则上面四条可能全是空真。
