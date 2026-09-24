# D-7 · 域名分配

**日期：** 2026-09-24（当日修订一次，见文末「修订记录」）
**状态：** 已落地并实测

---

## 分配

```
角色              域名                 可收录   推广   封了会怎样
──────────────────────────────────────────────────────────────────────────
官网 + 网页面板   91vpn.com            ✅ 唯一  广告   网页没了。【客户端照常能用】
客户端/节点 API   app.91app.shop       noindex  不     收不到钱、节点连不上面板
订阅              sub.91app.shop       noindex  不     换不了节点、加不了设备
后台              summer.91app.shop    —        不     只你自己进不去
入口域            marveo.fun           —        不     换一条 A 记录即可
```

`[!!]` **关键在于「面板」是两个东西，可以分开放：**

| | 搬家代价 | 现在在哪 |
|---|---|---|
| 面板**网页**（登录页、套餐页、帮助页…） | 便宜 —— 改 `APP_URL` 一行 | `91vpn.com` |
| 客户端 / 节点 **API** | 贵 —— 写死在已发出的客户端二进制里，要发新版 | `app.91app.shop`，**没动** |

`[S]` 做到这件事的是 `app/Http/Middleware/WebOnOfficialHost.php`：
非官网域收到**网页**请求时 302 到官网同路径，而这些路径整体放行 ——

```php
const PASSTHROUGH = ['sub', 'sub/*', 'mod_mu', 'mod_mu/*', 'pay', 'pay/*', 'up'];
// 另:后台域(ADMIN_HOST)整体豁免;/api/* 不在 web 组,天然不受影响
```

`[D]` 2026-09-24 逐条实测（在 `app.91app.shop` 上）：

```
/up                     200     /user       302 → 91vpn.com/user
/sub/<坏 token>         404     /help       302 → 91vpn.com/help
/mod_mu/nodes/100/info  401
/pay/epay/notify        200     ← 这条被跳会丢支付回调
/api/app/version        200     /api/plans  200     /api/auth/login  405
summer.91app.shop/admin 302 → summer.91app.shop/login   ← 没被跳回官网
```

`[D]` 节点 #100 在改动后 24 秒内正常上报，自报 `vless` 与面板一致。

---

## 这样分的理由

**曝光度要和「必须活着」反着排。** `91vpn.com` 要投广告、做 SEO、被搜索引擎
收录 —— 它是所有域名里最可能先被举报/被封的。所以放在它上面的只有
「封了只少新客」的东西：官网和网页。

**而「封了就收不到钱」的东西留在不推广的域上。** `91vpn.com` 被封时，
已安装的客户端仍然打得通 `app.91app.shop` 的 API —— 登录、续费、下单、
拉订阅全部照常。这是这套分配真正买到的东西。

`[S]` 「只让官网域被收录」由 `NoindexNonCanonicalHost` 中间件实现
（`X-Robots-Tag: noindex, nofollow`），判据是 `APP_URL` 的 host。
`[!]` 实际效果上，非官网域的**网页**是 302 到官网（`WebOnOfficialHost`
排在它前面），比 noindex 更强 —— 跳转会合并权重而不只是压制。
noindex 兜住的是不跳转的那些路径。

---

## 修订记录

### `[!!]` 2026-09-24 修订：本文初版的论证有一处实质错误

初版写的是「**别**把面板搬到 `91vpn.com`」，理由是「搬面板要发客户端新版」。
这个理由错在**把面板网页和客户端 API 混成了一个东西**：

- 搬网页只要改 `APP_URL`，几乎零成本
- 搬 API 才要发新版 —— 而这次**根本没搬 API**

初版还高估了另一项成本：当时按「已装出几千台客户端」估算发版代价，
而实际上一个真实用户都没有、客户端还没正式发。

初版正确、且仍然成立的部分：
- 曝光度要和「必须活着」反着排
- `91vpn.com` 是牺牲域，不该承载收款链路
- `91vpn.space/.store/.fun/.shop` 能从品牌 30 秒枚举出来，用它们做备用等于没备用

### 自检「订阅地址」已转绿

`[D]` 初版曾把这一项判为「长期接受的 warn」并写进
`LAUNCH-CHECKLIST` 的「不是缺陷」表。`APP_URL` 改成 `91vpn.com` 之后，
面板域（`91vpn.com`）与订阅域（`91app.shop`）已是**不同的可注册域**，
`ServiceReadiness` 这一项自己变成了 `ok`，两处记录均已更正。

---

## 待办（不拦上线）

- **`www.91vpn.com`** —— 投广告和自然搜索会有人打 www。建议在 Cloudflare 做
  www → 根域跳转，而不是并列服务两个地址
- **客户端 API 备用地址** —— `app.91app.shop` 是唯一的 API 域，单点。
  客户端内置「主地址不通换备用」的列表，在正式发版前加最便宜
- `[!]` 非官网域的网页跳转目前是 **302**。`app.91app.shop` 从未推广、没有
  搜索引擎历史，所以 302/301 差别很小；真要合并权重时改 301，但 301 会被
  浏览器长期缓存，改回来麻烦

---

## 相关

- `docs/decisions/entry-dispatch.md`（D-5 入口域名两层 CNAME）
- `docs/DEPLOYMENT.md` §3a（跑测试前绝不要 config:cache）、§3b（换服务器时 DNS 不用动）
- `app/Http/Middleware/WebOnOfficialHost.php`、`NoindexNonCanonicalHost.php`、`AdminHost.php`
