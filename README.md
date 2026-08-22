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

## 文档
设计文档与实现计划见 `docs/superpowers/`。
