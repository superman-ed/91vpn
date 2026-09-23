# 91VPN

一个机场式 VPN 面板（学习项目），用 Laravel 复刻同类产品的核心功能。

## 技术栈
- Laravel 11 (PHP 8.3) + Blade + Tailwind
- MySQL 8 + Redis
- Docker Compose

## 快速开始
```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
# 面板: http://localhost:8088
# 测试账号: admin@test.local / password
```

## 功能（第一阶段）
- 认证：注册（邮箱验证码+邀请码+算术码）/ 登录（2FA）/ 找回密码
- 用户中心：仪表盘 / 签到 / 节点设置（订阅链接）/ 公告
- 商店计费：套餐 / 订单 / 模拟支付 / 钱包
- 订阅下发：`/sub/{token}` 生成 Clash 配置
- 节点对接：`/mod_mu/*` WebAPI（拉用户 / 流量结算）

## 测试
```bash
docker compose exec app ./vendor/bin/pest
```

## 一次性脚本（复现 / 造数 / 核对）

**不要直接 `php artisan tinker 脚本.php`** —— 它连的是**真实库 `vpn`**，
不是 `vpn_test`。造出来的节点和用户会进真实订阅，而且**没有任何提示**：
脚本正常跑完，数据静静地写进去了。

用包装器：

```bash
tools/repro 脚本.php          # 跑在 vpn_test（默认）
tools/repro --real 脚本.php   # 真实库，需要输入 yes 确认
```

它把连接切到 `vpn_test`，并拼上 `tools/repro-guard.php` 兜底 ——
万一 env 覆盖没生效，守卫会在脚本的第一行之前就退出。

**直接调 artisan 也挡住了。** 这几条命令在生产库上会被拒绝：

```
tinker · db:seed · migrate:fresh · migrate:refresh · migrate:reset · db:wipe
```

它们会写库，而最常见的用法是"临时验一件事"—— 一旦连错库，现象是
**没有现象**：命令正常跑完，数据静静地进了生产。

`migrate`（前向）和全部定时任务**不在名单里** —— 它们本来就该在生产库上跑，
拦住等于堵死上线，而一个挡住正常操作的守卫三天内就会被人绕过去。

确实要动生产：`REPRO_ALLOW_REAL=1` 再执行，或 `tools/repro --real`。

## 部署到新服务器

看 **`docs/DEPLOYMENT.md`** —— 从空机器到「面板可登录、节点可连接、备份在跑」。

`[!!]` 两个最容易踩的：**不要跑 `db:seed`**（会造出 `admin`/`password` 这个默认管理员），
**入口域名的 DNS 必须关掉 Cloudflare 小黄云**（开着的话域名能解析但连不上）。

## 文档
设计文档与实现计划见 `docs/superpowers/`。
