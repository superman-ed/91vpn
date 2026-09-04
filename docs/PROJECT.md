# 91VPN 项目完整记录

> 学习性质的"机场"(VPN 订阅)产品,对标真站 **socloud.me** 复刻。最高原则:功能复刻真站(UI 自研)。
> 本文档是全局记录:做了什么、还缺什么、踩过哪些坑、怎么解决。
> 最后更新:2026-09-04。

---

## 0. 一句话现状

**功能面 ≈ 完成**:Web 面板 + 管理后台 + 客户端 API + 安卓 App(18 屏,中英双语,含免费体验档/设备管理/崩溃上报),原生集成已在真机跑通。
**离"能发布"还差**:① 真节点 VPS 联调(第一次用真节点验证 App 连接链路,最大未知数)② 发布准备(正式域名/keystore 签名/生产后端/支付商户号)③ 发布前域名安全(引导文件验签 + Pinning)。

---

## 1. 项目目标与技术栈

- **目标**:从零复刻一个机场 VPN,用于学习。对标 socloud.me(逆向证据全在后端仓库 `refs/`)。
- **三阶段**:① Web 面板 → ② 客户端(集成 Mihomo 内核)→ ③ 可选自研内核(玩具,学习)。
- **技术栈**
  - 后端:**Laravel 11 + Blade + Tailwind + MySQL + Redis + Docker**(选 Laravel 而非 SSPanel 同栈,因只求功能等价、开发快且安全)。
  - 客户端:**React Native 0.87.1(新架构/bridgeless + Hermes)+ TypeScript**,自研 RN 壳 + kr328 ClashService + **Mihomo(Clash.Meta)内核**,1:1 复刻真站 Vortex 架构。iOS 暂不自研(真站也甩 Shadowrocket)。
  - 节点端:真站用 **soga**(订阅里 `is_soga`/`soga_node_id` 实锤);我们复刻**生产用开源 XrayR/V2bX**(可审计、embed xray-core),soga 仅一次性本地验证。
- **翻墙协议**:**VMess**(alterId=0/AEAD、cipher=auto、udp=true、net=tcp/ws、可选 tls)。真站 100% VMess,节点用 IEPL 中转+多端口区分落地(落地真实 IP 从不暴露)。

## 2. 仓库与环境

| | 位置 | 说明 |
|---|---|---|
| 后端 | `/home/dev/web/91vpn`(本机)| Docker 容器 `91vpn-app-1`,bind-mount 改动即时生效;提交留本地 |
| 客户端 | `/home/dev/web/91vpn-android` → GitHub `superman-ed/91vpn-android` | 推 master |
| 配置引导 | GitHub `superman-ed/91vpn-config`(hosts.json) | 域名容灾用 |

- **测试**:后端 `docker exec 91vpn-app-1 php artisan test`(独立库 vpn_test);客户端 `npx tsc --noEmit` + `npx jest`。
- **开发环回**:本机改代码→推 GitHub;用户在**另一台 Windows 机 `D:\91vpn-android`** 跑测。每次验证第一步必须 `git pull`。纯 JS/图片改动 pull+reload;原生(Kotlin)改动才需重打 APK。
- **本机边界**:有 Node/Go、无 DISPLAY/KVM/真机 → 逻辑层能写+测;UI 可视化、原生集成、真机联调必须在开发机。

---

## 3. 后端(Web 面板 + API)——已完成

### 3.1 架构:机场的三条命脉
1. **订阅下发** `/sub/{token}`:token→user→按 `class/group` 筛节点→出 Clash YAML(模板 7 策略组 + 768 条规则)或 base64 vmess。token 与登录解耦(防封)。
2. **流量结算** `POST /mod_mu/users/traffic`:节点上报 `{user_id,u,d}` 原始字节 → `billed = 原始 × 节点倍率` → 累加 `users.u/d`,超 `transfer_enable` 断流。
3. **等级/分组**:拉用户名单时 `class >= node_class` 实现 VIP 分级 + `node_group` 分组。

### 3.2 节点对接(mod_mu / SSPanel WebAPI 兼容)
- `GET /mod_mu/users`(拉用户,返回 `data`+`users` 双键兼容 XrayR/自研 agent)
- `POST /mod_mu/users/traffic`(收流量)、`POST /mod_mu/users/aliveip`(限设备)
- `GET /mod_mu/nodes/{id}/info`(节点开机拉配置)、`POST` 同址(soga 心跳)
- `GET /func/detect_rules`(空=不审计)、`POST /users/detectlog`(空实现)、`GET /func/ping`
- **鉴权**:请求头 `X-Node-Secret`(推荐)或 `?key=`(兼容+告警)。一节点一 secret。
- **已用真 soga v2.16.1 本地验证契约通过**(拉 72 用户/节点信息/心跳/流量入账全通)。唯一没跑通的是 live 字节流过 soga——xray-client 26.7 ↔ soga 2.16 的 vmess 握手版本不兼容,**与面板无关**。

### 3.3 计费口径(权威,见记忆 billing-model)
- **周年重置(非日历 1 号)**:每用户按开通日 `next_reset_at`,`traffic:reset-monthly` 每天跑,到期清零 `u/d` + `transfer_enable` 归位 `base_transfer_enable`,未用不结转。
- 套餐两种重置类型:`monthly`(月配额)/ `none`(总量不重置)。
- 加油包:立即生效(`transfer_enable +=`),**重置日/到期清零不结转**。
- 排队生效:当前套餐未到期时新购 `queued` + `activate_at`,`orders:activate-due` 激活。
- 立即结束当前套餐(仅月付)、优惠券(支付成功才计次、按周期适用)、邀请返利(下线**每次充值**返 2.5%,非订单)。

### 3.4 支付(易支付 EPay 网关)
- 后台「站点设置」配 `epay_url/pid/key`;未配则收银台回退"模拟直付"。
- **发货三重保障**:① 异步回调 `notify`(实时,验签+校金额)② 主动对账 `payment:reconcile`(每 5min 查单补漏)③ 超时关单 `orders:expire-pending`(每 10min,关单前再查一次防误杀)。均走并发安全幂等入口 `BillingService::settleOrder`。

### 3.5 客户端 API(无状态 Bearer,~40 端点)
- 认证 `Authorization: Bearer <api_token>`,中间件 `client.token`;信封 `{ret,data,msg}`。
- 覆盖:认证/用户/节点列表/公告/签到/改密改昵/**设备上报+列表+下线**/套餐/订单全流程/钱包/工单/邀请/消息/**崩溃上报**/app 配置。
- **DRY**:`RegistrationService`(建号+邀请归因)、`OrderService`(下单+券)网页与 API 共用;扣款/发货统一 `BillingService`。
- **唯一绕不开第三方**:支付网关那一下返回 `{status:'redirect', pay_url}`,App 开浏览器打开易支付收银台。

### 3.6 免费体验档(freemium,2026-09-03 新增)
- **意图**:签到发的是流量(每日随机 100–500MB),让**非会员也能用**做免费体验→转化订阅。
- **免费节点 = `node_class=0`**;非会员 class=0 自然只连免费节点(付费套餐 class≥1)。**运营需建一个 node_class=0 的免费节点**。
- **封顶**:非会员签到 `transfer_enable` 封顶 `free_traffic_cap`(站点设置 `free_traffic_cap_gb`,默认 2GB,填 0 关闭)。**每月再生**(reset 命令清零 u/d)。防无限累积白嫖。
- **节点侧**:免费节点不卡会员期,但非会员额外受 `u+d < free_cap` 约束(防过期会员拿旧大额度白烧);免费用户下发节点自带限速。
- **列表 vs 可连**:`/api/servers` 全量返回节点 + `locked` 标记(展示丰富度);订阅配置只放能连的。App 里付费节点半透明,点击弹「订阅解锁」。

### 3.7 设备上报 + 崩溃日志(自建)
- **设备**:`devices` 表 + `POST /api/device/report`(登录后)+ `GET /api/devices`/`DELETE /api/devices/{id}`;后台「设备统计」。App 生成可重置随机 device_id(非 IMEI/MAC)。
- **崩溃**:`crash_logs` 表 + `POST /api/crash`(公开,token 可选,限流);message 归一化 sha1 前 40 位作 fingerprint 聚合;后台「崩溃日志」按 bug 聚合 + 详情看堆栈。**自建替代 Sentry**(数据自留、无第三方、无 GMS 依赖),只抓 JS 层错误。

### 3.8 安全基线(规避 SSPanel 默认坑)
argon2id 密码 / 全站 CSRF / 一节点一 secret / 支付回调严格验签 / 订阅 token 可重置 / API 限流 / Web 用 session 不做 IP 绑定(客户端用 token)。

---

## 4. 安卓客户端 App——功能完成 + 原生跑通

### 4.1 设计
- **自研 UI**(不是真站的):浅色暖调,主色珊瑚红 `#F36659`,暖渐变 `#FFD9AF→#FF6C83`。设计唯一真实来源 = 我们自己的 Figma(逐屏对齐)。
- 免登录架构:打开即进主界面(游客态),仅购买/连接/账号操作才 `requireAuth`(底部登录浮层)。
- 账户体系:账户名 + 密码(无邮箱、无验证码),忘密码走在线客服。

### 4.2 已复刻/完成的屏(18)
加速器(连接)、线路选择、充值、确认支付、我的、设置、钱包、邀请、订单、消息、工单+详情、客服(Crisp/Linking)、帮助中心+详情、协议、登录浮层、注册、设备列表、签到弹窗。**全部中英双语**(自研轻量 i18n,见 §6)。

### 4.3 机场核心内核集成(★2026-08-31 里程碑:原生跑通)
- 服务器无头自编 Mihomo(NDK r29 + MetaCubeX,产出 `core-alpha-release.aar` 含 libclash.so 全 4 ABI)。
- `ClashVpnService`(自写精简 VpnService,TUN → `Clash.load(目录)` → startTun)+ `ClashModule`(BaseReactPackage 注册,JS 用 TurboModuleRegistry.get 取)。
- **真机验证:授权→起 TUN→加载配置→接管流量→规则分流 全通。唯一差真节点**(demo 节点地址是占位 `vmess-server:10086` 解析不了)。
- **核心/TUN 解耦**(照 SoCloud):`prepareCore()` 仅加载内核(测速用,不建 TUN);`testDelay()` 测速;`selectNode()` 下发选中节点(`PUT /proxies/{group}`,组名懒探测)。**写完+单测(60 passed),待真机+VPS 验证**。

### 4.4 域名容灾(App 怎么找到后端 API 域名)
四来源合并去重逐个探活取第一个通的:① 上次成功 `api_host_last` ② 后台下发缓存 `api_hosts` ③ **jsDelivr 引导文件**(`superman-ed/91vpn-config/hosts.json`,连后端前可读、封不掉)④ 内置 `SEED_HOSTS`。运行期主域名失败自动热切换重试。**现均为占位 cloudflare 隧道,正式发布换真实固定域名。**

### 4.5 健壮性/安全(已修的审计项,详见 android 仓库 `docs/AUDIT.md`)
- P0 全 4:IPv6 收进隧道防泄漏 / 连接状态改**原生事件驱动**(不再假显示已连接)/ 登出硬复位(断 VPN+清明文配置)/ ABI 拆分(288MB→~80MB arm 包)。
- P1:网络超时 / AppState 回前台校正 / 域名容灾重试 / onRevoke kill-switch / ErrorBoundary / 启动并发探活 / 通知权限申请 / 结算页取消预勾选 / **netinfo 网络切换重连** / **token 加密存储(自写 SecureStore 原生模块 EncryptedSharedPreferences)** / **请求竞态守卫(useLoadGuard)**。
- P2:fd 泄漏修复 / controller secret 用 SecureRandom / 各屏缓存(stale-while-revalidate)/ 网络错误归一文案 / 协议可点阅读 / 隐私文案对齐实现 / **设备上报接线** / **登出残留 key 清理** 等。

---

## 5. 已知踩过的坑与解决(精选)

### 后端 / 契约
- **soga panel type**:值是 `sspanel-uim` 不是 `sspanel`(后者报 unknown panel;从二进制 grep 出)。
- **soga 心跳**走 `POST /mod_mu/nodes/{id}/info`(非 `/func/ping`)→ 原只有 GET 报 405,补了 POST 处理。
- **soga DNS 坑**:`webapi_url` 用容器 IP(非主机名),否则 default_dns=8.8.8.8 解析内部主机名报 127.0.53.53。
- **易支付签名**:文档正文说 sign_type 不参与签名、示例代码却算进去(自相矛盾)——按正文实现,联调报签名错就改为保留 sign_type。
- **易支付域名未授权**:`trycloudflare.com` 临时域名会变+网关要绑授权域名 → 正式用固定域名。
- **收银台点支付无反应**:① Turbo 拦截表单跨域跳转 → 表单加 `data-turbo="false"`;② `data-confirm` 是 UJS 保留属性(无值触发空白 confirm)→ 本项目自定义确认统一用 `data-dgr`,别再用 `data-confirm`。
- **后台流量配额输入框**:`step="0.1"` 只收 0.1 倍数,而 `bytes_to_gb` 保留 2 位小数(如 1.36),预填值即被浏览器判"无效数值" → 改 `step="any"`。

### 客户端 / RN 新架构
- **RN 0.87 新架构 `NativeModules` 为空** → 旧式原生模块必须 `BaseReactPackage` + `TurboModuleRegistry.get` 取。
- **TUN fd 二次 close 致 App 自动关闭**(坑#9):`establish().detachFd()` 把 fd 交内核,stop 只 `Clash.stopTun()` 不自己 close。**原则:谁拥有 fd 谁负责关**(Win/Mac 同理)。
- **国内构建机踩 google 源墙**:`react-native-webview` 硬拉 AGP `gradle:7.0.4` 连不上 `dl.google.com` → 放弃 WebView,客服改用 `Linking.openURL` 开 Crisp。**教训:能不加原生依赖就不加,必须加时优先纯 JS/Linking。**
- **react-native-svg 15.12.1** import `@react-native/assets-registry` 但 RN 0.87 没列为依赖 → Metro 500 红屏。修:显式 `npm i @react-native/assets-registry@0.87.1`。
- **keychain buildscript 无守卫硬拉 AGP 8.3.1 撞墙** → 弃 keychain,自写 SecureStore 原生模块(EncryptedSharedPreferences)。
- **ABI 拆分**在 Gradle 9.4.1 上 `taskNames` 检测失效 → 改 `-PabiSplit` 属性开关。
- **界面"很胖"**:`S = width/375` 无上限致宽机整体放大 → `uiScale = min(width/375, 1)`(窄屏缩、宽屏不放大),首页按屏高 `min(width/375, height/812, 1)`,全局字体 `maxFontSizeMultiplier=1.15`。
- **tsconfig 排除 UI 目录**(screens/app/components/App.tsx,含 RN 运行时类型)——这些本机 tsc 不校验,类型以开发机构建为准;`src/native`+`src/core` 被校验,故不能在那里 import 'react-native'。
- **i18n 竞态/遮蔽**:模块级映射表(周期/状态/流水/支付方式)改存 i18n key、渲染时 `t()` 解析;局部变量名 `t` 会遮蔽 i18n(TicketDetail 用 `tr`);memo 组件不订阅 context,文案走 prop。
- **导航嵌套**:`Shop` 是 `Main` 里的 tab,从顶层 Stack 屏要 `navigate('Main', {screen:'Shop'})`。
- **Metro/真机**:每次验证先 `git pull`;`run-android` 才会 `adb reverse` 让真机连 Metro;JS 红屏 `--reset-cache`;Windows 机 package-lock 漂移挡 pull → pull 前 `git checkout -- package-lock.json`。

---

## 6. 中英双语(i18n)——已全屏落地

- **自研轻量方案**(无第三方):`src/app/i18n.ts` 两张 zh/en Dict(点分命名空间)+ `translate(lang,key,params)`(缺失回退 zh 再回退 key)+ `deviceLang()`;context 提供 `lang/setLang/t`,切换→全树重渲染;设置页有语言切换。
- **覆盖**:全部屏 + 弹窗。共享键 period.*/legal.*/common.*。
- **刻意保留原文**:LegalScreen 兜底法律正文(生产内容由后台单语下发,不做专业法律翻译)、ICP 备案号、语言自称名(中文/English)。

---

## 7. 还缺什么(按优先级)

### ① 真节点 + VPS 联调(核心里程碑,进行中)
把 mock/占位节点换成真节点,**第一次真机验证 App 连接链路**:`拉订阅→加载内核→测速→选节点→建 TUN→真流量→上报计费`。
- 面板侧契约已就绪(mod_mu,已用真 soga 验通);运营需建 `node_class=0` 免费节点 + 若干付费节点。
- 节点程序建议 XrayR(`PanelType: SSpanel`,`ApiHost`=面板地址,`ApiKey`=节点 secret,`NodeID`=节点 id,`NodeType: V2ray`)。
- **前提:面板要有公网地址**(现在是本地 Docker),节点程序才连得上来拉用户。
- **待真机验证的不确定点**(见 airport-core 记忆):无 TUN 下 external-controller 能否测速;代理名匹配(node.name vs 订阅转换后的名);proxy-group 组名;'auto' 节点自选。

### ② 发布准备(上架前必做)
- 正式**域名**(替换三处占位隧道:SEED_HOSTS 发版换、jsDelivr hosts.json、后台 api_hosts 列表)。
- **keystore 正式签名**(`android/keystore.properties`,**丢了无法更新已上架 App,务必备份**)。
- 首次开 R8 的正式包(`gradlew assembleRelease -PabiSplit -PminifyRelease`)**真机验证**(序列化/反射路径别崩)。
- 后端上生产 + HTTPS;支付网关(易支付)**正式商户号**。
- 后台法务条款占位【运营方名称】【适用法律地区】替换;Crisp website_id。
- 发布渠道:面向国内**大概率官网直接下 APK**(Google Play 会卡第三方支付卖订阅违规)。

### ③ 发布前安全硬项
- **P1-8 域名引导文件验签 + 证书/公钥 Pinning**(防 hosts.json 被替换接管到钓鱼后端)——绑定正式域名,和 ② 一起做。

### 其他(可选/量大再做)
- geo 库 ~12MB 随包 → 首启按需下载瘦身(P2-10)。
- 原生层崩溃(Java/Kotlin/C)自建抓不到 → 量大了再补 GlitchTip(自托管)/Sentry。
- 明确**不做**:P2-5 FLAG_SECURE(伤截图分享)、P2-7 剪贴板(要分享)。

---

## 8. 相关文档索引
- 后端 `refs/`:socloud 逆向全套(私有,.gitignore)。
- android 仓库:`docs/AUDIT.md`(六维度审计+勾选)、`docs/RELEASE.md`(发布步骤)、`docs/MAINTENANCE.md`(9 条踩坑表)、`docs/DEV_SETUP.md`(开发机 S0→S8)。
- 会话记忆:后端仓库无,存于 Claude 项目记忆(billing-model / payment-gateway / node-traffic-reporting / android-client / airport-core-testspeed-selectnode / android-i18n / client-device-reporting 等)。
